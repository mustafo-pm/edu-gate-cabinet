<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation for PSP-owned data. Mirrors ScopedToMerchant but keys on
 * `psp_id` and the `psp` guard.
 */
trait ScopedToPsp
{
    public static function bootScopedToPsp(): void
    {
        static::addGlobalScope('psp', function (Builder $builder): void {
            if ($id = auth('psp')->user()?->psp_id) {
                $builder->where($builder->getModel()->getTable().'.psp_id', $id);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->psp_id) && ($id = auth('psp')->user()?->psp_id)) {
                $model->psp_id = $id;
            }
        });
    }
}
