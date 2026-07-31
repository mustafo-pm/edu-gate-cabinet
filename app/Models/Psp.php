<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PspStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A Payment Service Provider. Also the Sanctum token-holder and `api`-guard
 * user model for server-to-server calls, so PSP API requests resolve to it.
 */
class Psp extends Authenticatable implements AuditableContract
{
    use Auditable;
    use HasApiTokens;

    protected $fillable = [
        'name', 'code', 'status', 'commission_bps',
        'contact_name', 'contact_phone', 'contact_email', 'webhook_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => PspStatus::class,
            'commission_bps' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(PspUser::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Current deposit balance in tiyin (latest ledger snapshot). */
    public function depositBalance(): int
    {
        return (int) ($this->deposits()->latest('id')->value('balance_after') ?? 0);
    }
}
