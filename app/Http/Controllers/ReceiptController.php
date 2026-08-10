<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Support\Qr;
use App\Support\ReceiptLookup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public receipt page — no login, reached by scanning a QR.
 *
 * Because it is public, two things are load-bearing:
 *
 *  • The code in the URL is random, not the payment id, so the page cannot be
 *    enumerated (see PaymentReceipt).
 *  • A wrong code is rate limited far more aggressively than a right one. A
 *    real visitor follows a working link; someone guessing produces nothing but
 *    misses, so counting misses separately catches them without slowing anyone
 *    else down.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, string $code): View|Response
    {
        if ($limited = $this->throttle($request)) {
            return $limited;
        }

        $this->setPageLocale($request);

        $receipt = $this->find($request, $code);

        if (! $receipt) {
            // 404 with the same page and timing whether the code is malformed,
            // unknown or simply wrong — nothing here tells a guesser they are
            // getting warmer.
            return response()->view('receipt.not-found', [], 404);
        }

        return response()
            ->view('receipt.show', [
                'receipt' => $receipt,
                'qr' => Qr::svg($receipt->url()),
                'checkedAt' => now(),
            ])
            // The page now differs by device language, so any shared cache in
            // front of us must not hand the Russian copy to an Uzbek visitor.
            ->header('Vary', 'Accept-Language');
    }

    /**
     * Language for this one request: ?lang= if given, otherwise the device's.
     *
     * An explicit choice always wins, because the switcher has to work and
     * because a link shared in one language must open in that language for
     * whoever receives it. Failing that we read the browser's own preference —
     * the payer never asked to be here, they scanned a code, so the page should
     * already be in their language when it loads.
     *
     * Falls back to Uzbek when nothing matches, which is also what Symfony
     * returns for an unknown header since Uzbek heads the offered list.
     *
     * Deliberately not the session locale the cabinet uses: a payer switching
     * this page to Russian must not change the language of a cabinet that
     * happens to be signed in in the same browser.
     */
    private function setPageLocale(Request $request): void
    {
        $offered = (array) config('receipt.locales');
        $wanted = (string) $request->query('lang');

        app()->setLocale(
            in_array($wanted, $offered, true)
                ? $wanted
                : ($request->getPreferredLanguage($offered) ?? $offered[0]),
        );
    }

    /**
     * The PDF is built in memory and streamed straight to the browser.
     *
     * Nothing is written to the server: a receipt is personal data, and a
     * directory quietly filling with other people's names and amounts is a
     * liability with no upside — regenerating costs milliseconds.
     */
    public function pdf(Request $request, string $code): StreamedResponse|Response
    {
        if ($limited = $this->throttle($request)) {
            return $limited;
        }

        $this->setPageLocale($request);

        $receipt = $this->find($request, $code);

        if (! $receipt) {
            return response()->view('receipt.not-found', [], 404);
        }

        $pdf = Pdf::loadView('receipt.pdf', [
            'receipt' => $receipt,
            'qr' => Qr::pngDataUri($receipt->url(), 160),
            'checkedAt' => now(),
        ])->setPaper('a5');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'EduGate-'.$receipt->number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function find(Request $request, string $code): ?PaymentReceipt
    {
        return ReceiptLookup::find($request, $code);
    }

    /** @return Response|null a response when the caller must be turned away */
    private function throttle(Request $request): ?Response
    {
        return ReceiptLookup::throttled($request)
            ? response()->view('receipt.throttled', [], 429)
            : null;
    }
}
