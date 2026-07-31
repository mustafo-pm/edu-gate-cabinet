<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation for merchant-owned data. A global scope guarantees a
 * forgotten where-clause can never leak another institution's rows, and new
 * records auto-inherit the signed-in merchant's id.
 *
 * The scope only activates when the `merchant` guard is authenticated, so the
 * admin panel and the API (which authenticate on other guards) are unaffected.
 */
trait ScopedToMerchant
{
    public static function bootScopedToMerchant(): void
    {
        static::addGlobalScope('merchant', function (Builder $builder): void {
            if ($id = auth('merchant')->user()?->merchant_id) {
                $builder->where($builder->getModel()->getTable().'.merchant_id', $id);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->merchant_id) && ($id = auth('merchant')->user()?->merchant_id)) {
                $model->merchant_id = $id;
            }
        });
    }
}
