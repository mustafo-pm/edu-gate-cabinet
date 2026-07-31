<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\MerchantStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\PaymentException;
use App\Models\Merchant;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Step 1 of a payment: look up the student and amount owed, and issue a
 * short-lived check (TTL 15m) that /confirm will finalize.
 */
class CheckPayment
{
    public const TTL_MINUTES = 15;

    /** @return array{check_id:string, student:Student, amount_owed:int, expires_at:string} */
    public function handle(int $merchantId, string $studentIdNumber, ?int $amountTiyin = null): array
    {
        $merchant = Merchant::findOrFail($merchantId);

        if ($merchant->status !== MerchantStatus::Active) {
            throw PaymentException::institutionInactive();
        }

        // No tenant guard on the API — resolve the student explicitly by merchant.
        $student = Student::withoutGlobalScopes()
            ->where('merchant_id', $merchantId)
            ->where('student_id_number', $studentIdNumber)
            ->firstOrFail();

        $outstanding = (int) $student->schedules()
            ->withoutGlobalScopes()
            ->whereIn('status', [ScheduleStatus::Unpaid->value, ScheduleStatus::Partial->value, ScheduleStatus::Overdue->value])
            ->get()
            ->sum(fn ($s) => $s->outstanding());

        $amount = $amountTiyin ?? $outstanding;

        $checkId = 'chk_'.Str::random(24);

        Cache::put("payment_check:{$checkId}", [
            'merchant_id' => $merchant->id,
            'student_id' => $student->id,
            'amount' => $amount,
        ], now()->addMinutes(self::TTL_MINUTES));

        return [
            'check_id' => $checkId,
            'student' => $student,
            'amount_owed' => $amount,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->toIso8601String(),
        ];
    }
}
