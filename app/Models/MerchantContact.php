<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantContactKind;
use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone or address for one desk at an institution.
 *
 * `is_public` is the line between an internal directory and something a payer
 * can be handed. Off by default: a number ends up on a public receipt only
 * because somebody chose to put it there.
 */
class MerchantContact extends Model
{
    use ScopedToMerchant;

    protected $fillable = [
        'merchant_id', 'kind', 'title', 'person_name',
        'phone', 'email', 'is_public', 'sort_order',
    ];

    /** Mirrors the database defaults, so a new model is right before reload. */
    protected $attributes = [
        'is_public' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => MerchantContactKind::class,
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** The custom title if one was given, otherwise the kind's own label. */
    public function displayTitle(): string
    {
        return filled($this->title) ? $this->title : $this->kind->label();
    }
}
