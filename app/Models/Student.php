<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use ScopedToMerchant;

    protected $fillable = [
        'merchant_id', 'department_id', 'student_id_number',
        'first_name', 'last_name', 'middle_name', 'date_of_birth',
        'status', 'parent_name', 'parent_phone',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }
}
