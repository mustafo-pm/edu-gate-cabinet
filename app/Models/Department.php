<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use ScopedToMerchant;

    protected $fillable = ['merchant_id', 'parent_id', 'name', 'code'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
