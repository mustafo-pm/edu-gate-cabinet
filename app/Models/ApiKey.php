<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApiEnvironment;
use App\Models\Concerns\ScopedToPsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use ScopedToPsp;

    protected $fillable = [
        'psp_id', 'name', 'key_id', 'secret_hash', 'environment',
        'last_used_at', 'revoked_at',
    ];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'environment' => ApiEnvironment::class,
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function psp(): BelongsTo
    {
        return $this->belongsTo(Psp::class);
    }
}
