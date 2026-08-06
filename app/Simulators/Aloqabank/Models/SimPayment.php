<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment order inside the simulated bank.
 *
 * The statuses are the bank's own Cyrillic strings, kept verbatim so our client
 * code is tested against exactly what it will receive:
 *   Введен   — accepted, not yet settled. Keep polling.
 *   Проведен — settled.
 *   Удален   — rejected by the core banking system.
 */
class SimPayment extends Model
{
    public const ENTERED = 'Введен';

    public const EXECUTED = 'Проведен';

    public const DELETED = 'Удален';

    protected $table = 'sim_aloqabank_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'commission_amount' => 'integer',
            'debited' => 'boolean',
            'execute_after' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SimService::class, 'service_id');
    }

    /**
     * Settle lazily on read.
     *
     * A real bank moves the payment on its own schedule; here the transition is
     * driven by the clock instead of a worker, so the simulator needs no queue
     * to reproduce the asynchronous behaviour. `settles_to` lets a test or a
     * magic account pin the eventual outcome — including staying Введен forever,
     * which is the case most integrations forget to handle.
     */
    public function settleIfDue(): self
    {
        if ($this->payment_status !== self::ENTERED) {
            return $this;
        }

        if ($this->settles_to === self::ENTERED) {
            return $this;                       // deliberately stuck
        }

        if ($this->execute_after && now()->lt($this->execute_after)) {
            return $this;                       // not yet
        }

        $this->payment_status = $this->settles_to;
        $this->executed_at = now();
        $this->save();

        // A rejected order never leaves the bank, so the money comes back — but
        // only if it was taken in the first place. An order the service could
        // not afford is accepted and then rejected without ever being debited;
        // refunding that would mint money out of nothing.
        if ($this->payment_status === self::DELETED && $this->debited) {
            SimService::whereKey($this->service_id)
                ->increment('balance', $this->amount + $this->commission_amount);

            $this->forceFill(['debited' => false])->save();
        }

        return $this;
    }
}
