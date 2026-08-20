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

    /**
     * "Faculty of Economics · Chair of Finance" — the parent carries most of
     * the meaning, and a bare chair name is ambiguous across faculties.
     */
    public function path(): string
    {
        return $this->parent
            ? $this->parent->name.' · '.$this->name
            : $this->name;
    }

    /**
     * Ids this department may not be re-parented under: itself and everything
     * below it.
     *
     * Without this a two-click mistake makes a department its own ancestor,
     * and every later walk of the tree loops forever. The schema cannot
     * express the constraint, so it lives here.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /** Whether removing this row would strand students or child departments. */
    public function isInUse(): bool
    {
        return $this->students()->exists() || $this->children()->exists();
    }
}
