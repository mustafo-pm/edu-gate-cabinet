<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Merchant extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'name', 'type', 'status', 'stir', 'mfo', 'bank_account', 'bank_name',
        'commission_bps', 'contact_name', 'contact_phone', 'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'type' => MerchantType::class,
            'status' => MerchantStatus::class,
            'commission_bps' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
