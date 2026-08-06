<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the partner's services. `type` decides whether the card fields are
 * mandatory on a payment — see GET /api/v2/services in the bank's docs.
 */
class SimService extends Model
{
    public const WORKING_WITH_CARD = 'WORKING_WITH_CARD';

    public const CARD_IS_OPTIONAL = 'CARD_IS_OPTIONAL';

    protected $table = 'sim_aloqabank_services';

    // `id` is fillable on purpose: serviceId is assigned by the BANK, not by us,
    // so the simulator has to be able to reproduce their numbering (33, 34, 35)
    // rather than hand out its own auto-increment values.
    protected $fillable = ['id', 'partner_id', 'name', 'activated', 'type', 'account', 'balance'];

    protected function casts(): array
    {
        return ['activated' => 'boolean', 'balance' => 'integer'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(SimPartner::class, 'partner_id');
    }

    /** True when refNumber / cardType / cardNumber must be present. */
    public function requiresCard(): bool
    {
        return $this->type === self::WORKING_WITH_CARD;
    }
}
