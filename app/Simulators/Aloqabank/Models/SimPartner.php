<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A partner account inside the simulated bank. Authenticates over HTTP Basic. */
class SimPartner extends Model
{
    protected $table = 'sim_aloqabank_partners';

    protected $fillable = ['name', 'username', 'password', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(SimService::class, 'partner_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SimPayment::class, 'partner_id');
    }
}
