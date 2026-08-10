<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentReceipt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A receipt as the public endpoint returns it.
 *
 * This is read by anyone holding the link, so the shape is deliberately
 * narrower than the payment behind it: no internal ids, no commission, no
 * institution bank details. The payer sees what they paid and to whom.
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

            // Live, never the printed snapshot: a receipt on paper keeps
            // saying "paid" long after a refund. `valid` is the single field a
            // caller should branch on.
            'valid' => $this->isValid(),
            'status' => $status->value,
            'status_label' => $status->label(),

            'institution' => $this->institution_name,
            'student' => [
                'name' => $this->student_name,
                'number' => $this->student_number,
            ],

            'amount' => $this->amount,                       // tiyin
            'amount_formatted' => Money::format($this->amount),
            'currency' => 'UZS',

            'paid_via' => $this->psp_name,
            'paid_at' => $this->paid_at?->toIso8601String(),

            'url' => $this->url(),

            // When this answer was produced. A forwarded screenshot carries a
            // stale timestamp, which is how a live check is told from an image.
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
