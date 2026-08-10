<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentReceipt;
use App\Support\Money;
use App\Support\StatusPalette;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A receipt as the public endpoint returns it.
 *
 * Two things shape this beyond the usual.
 *
 * It is read by anyone holding the link, so the payload is deliberately
 * narrower than the payment behind it: no internal ids, no commission, no net
 * amount. The payer sees what they paid and to whom.
 *
 * And the caller is a static page on another host with no backend of its own,
 * so everything it needs to render is here — field captions and status names in
 * every configured language at once, plus the status colour and icon. It should
 * never have to keep its own copy of our wording, which would drift the moment
 * a translation is corrected here.
 *
 * @mixin PaymentReceipt
 */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status();

        return [
            'number' => $this->number,
            'code' => $this->code,

            // The one field a caller should branch on. `status.value` says why.
            'valid' => $this->isValid(),

            'status' => [
                'value' => $status->value,
                'label' => $this->translations('cabinet.status.'.$status->value),
                'color' => StatusPalette::for($status->color()),
                // Colour alone must never carry the meaning — the brand guide
                // requires a glyph or a word beside it, so the name comes too.
                'icon' => $status->icon(),
            ],

            'institution' => $this->institution_name,
            'student' => [
                'name' => $this->student_name,
                'number' => $this->student_number,
            ],

            'amount' => $this->amount,                       // tiyin
            'amount_formatted' => Money::format($this->amount),
            'currency' => 'UZS',

            'paid_via' => [
                'name' => $this->psp_name,
                // Null unless the provider's logo is published on the partner
                // wall; the page falls back to the name. See PaymentReceipt.
                'logo_url' => $this->pspLogoUrl(),
            ],

            'paid_at' => $this->paid_at?->toIso8601String(),

            'url' => $this->url(),

            // When this answer was produced. A forwarded screenshot carries a
            // stale timestamp, which is how a live check is told from an image.
            'checked_at' => now()->toIso8601String(),

            // Captions for the fields above, so the page can switch language
            // without asking us again.
            'labels' => [
                'status' => $this->translations('receipt.status'),
                'number' => $this->translations('receipt.number'),
                'institution' => $this->translations('receipt.institution'),
                'student' => $this->translations('receipt.student'),
                'amount' => $this->translations('receipt.amount'),
                'paid_via' => $this->translations('receipt.via'),
                'paid_at' => $this->translations('receipt.paid_at'),
                'checked_at' => $this->translations('receipt.checked_at'),
                'confirmed' => $this->translations('receipt.confirmed'),
                'not_valid' => $this->translations('receipt.not_valid'),
                'qr_hint' => $this->translations('receipt.qr_hint'),
            ],
        ];
    }

    /**
     * One translation key in every language the endpoint serves.
     *
     * @return array<string, string>
     */
    private function translations(string $key): array
    {
        $out = [];

        foreach ((array) config('receipt.locales') as $locale) {
            $out[$locale] = __($key, [], $locale);
        }

        return $out;
    }
}
