<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class PspUser extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected string $guard_name = 'psp';

    protected $fillable = [
        'psp_id', 'name', 'email', 'phone', 'password', 'must_change_password', 'password_changed_at', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function psp(): BelongsTo
    {
        return $this->belongsTo(Psp::class);
    }
}
