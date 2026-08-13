<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public page for one legal document.
 *
 * Server-rendered on purpose. Unlike the receipt this page SHOULD be indexed —
 * people search for "edu-gate oferta" — and a document that only appears once a
 * script has run is a document that is missing when it matters.
 */
class LegalController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $this->setPageLocale($request);

        $doc = LegalDocument::active()->where('slug', $slug)->first();
        $version = $doc?->currentVersion();

        if ($version === null) {
            return response()->view('legal.not-found', [], 404);
        }

        return response()->view('legal.show', [
            'document' => $doc,
            'version' => $version,
            'upcoming' => $doc->upcomingVersion(),
        ])->header('Vary', 'Accept-Language');
    }

    /** Same rule as the receipt: ?lang= wins, else the device's, else Uzbek. */
    private function setPageLocale(Request $request): void
    {
        $offered = ['uz', 'ru', 'en'];
        $wanted = (string) $request->query('lang');

        app()->setLocale(
            in_array($wanted, $offered, true)
                ? $wanted
                : ($request->getPreferredLanguage($offered) ?? $offered[0]),
        );
    }
}
