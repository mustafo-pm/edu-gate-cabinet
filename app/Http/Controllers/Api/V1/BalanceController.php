<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LedgerType;
use App\Models\Deposit;
use App\Models\Psp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends ApiController
{
    /**
     * GET /api/v1/balance — the PSP's prepaid deposit.
     *
     * Worth having as its own endpoint rather than leaving PSPs to add up the
     * ledger: every payment is refused once this reaches zero, so a provider
     * needs to watch it, and a number they compute themselves is a number that
     * can disagree with ours at exactly the wrong moment.
     *
     * Reads the running balance off the newest ledger row rather than summing
     * credits and debits — the ledger is append-only and each row already
     * carries the balance after it, so a sum is both slower and a second way to
     * get the same answer.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Psp $psp */
        $psp = $request->user();

        $latest = Deposit::withoutGlobalScopes()
            ->where('psp_id', $psp->id)
            ->orderByDesc('id')
            ->first();

        $balance = (int) ($latest->balance_after ?? 0);

        return $this->ok([
            'balance' => $balance,          // tiyin
            'currency' => 'UZS',

            // Enough to answer "is my float running out?" without a second call.
            'last_movement' => $latest === null ? null : [
                'type' => $latest->type instanceof LedgerType
                    ? $latest->type->value
                    : (string) $latest->type,
                'amount' => (int) $latest->amount,
                'reference' => $latest->reference,
                'at' => $latest->created_at?->toIso8601String(),
            ],
        ]);
    }
}
