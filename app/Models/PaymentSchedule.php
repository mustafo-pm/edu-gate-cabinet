<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSchedule extends Model
{
    use ScopedToMerchant;

    protected $fillable = [
        'merchant_id', 'student_id', 'title', 'period',
        'amount', 'paid_amount', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',       // tiyin
            'paid_amount' => 'integer',  // tiyin
            'due_date' => 'date',
            'status' => ScheduleStatus::class,
        ];
    }

    /** Remaining amount owed, in tiyin. */
    public function outstanding(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
