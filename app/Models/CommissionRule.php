<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionScope;
use App\Enums\MerchantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CommissionRule extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'scope', 'merchant_id', 'psp_id', 'category',
        'rate_bps', 'fixed_fee', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scope' => CommissionScope::class,
            'category' => MerchantType::class,
            'rate_bps' => 'integer',
            'fixed_fee' => 'integer', // tiyin
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function psp(): BelongsTo
    {
        return $this->belongsTo(Psp::class);
    }
}
