<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_transaction_id' => $this->partner_transaction_id,
            'status' => $this->status->value,
            'amount' => $this->amount,                 // tiyin
            'commission_amount' => $this->commission_amount, // tiyin
            'net_amount' => $this->net_amount,         // tiyin
            'currency' => 'UZS',
            'merchant_id' => $this->merchant_id,
            'student_id' => $this->student_id,
            'gateway' => $this->gateway,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
