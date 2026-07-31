<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\CommissionScope;
use App\Models\CommissionRule;
use App\Models\Merchant;
use App\Models\Psp;

/**
 * Resolves the EduGate commission (in tiyin) for a payment.
 *
 * Priority: category > merchant > psp > global (CommissionScope::weight()).
 * Among equal scopes, higher `priority` wins. All arithmetic is integer tiyin.
 */
class ResolveCommission
{
    public function handle(Psp $psp, Merchant $merchant, int $amountTiyin): int
    {
        $rule = CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($psp, $merchant) {
                $q->where('scope', CommissionScope::Global->value)
                    ->orWhere(fn ($qq) => $qq->where('scope', CommissionScope::Merchant->value)->where('merchant_id', $merchant->id))
                    ->orWhere(fn ($qq) => $qq->where('scope', CommissionScope::Psp->value)->where('psp_id', $psp->id))
                    ->orWhere(fn ($qq) => $qq->where('scope', CommissionScope::Category->value)->where('category', $merchant->type->value));
            })
            ->get()
            ->sort(function (CommissionRule $a, CommissionRule $b) {
                return [$b->scope->weight(), $b->priority] <=> [$a->scope->weight(), $a->priority];
            })
            ->first();

        if (! $rule) {
            // Fall back to the merchant's own default rate if no rule matches.
            $rateBps = $merchant->commission_bps;
            $fixed = 0;
        } else {
            $rateBps = $rule->rate_bps;
            $fixed = $rule->fixed_fee;
        }

        // commission = amount * rateBps / 10000  (bps → fraction), rounded to tiyin, plus fixed fee.
        $commission = intdiv($amountTiyin * $rateBps, 10000) + $fixed;

        // Commission can never exceed the amount.
        return (int) min($commission, $amountTiyin);
    }
}
