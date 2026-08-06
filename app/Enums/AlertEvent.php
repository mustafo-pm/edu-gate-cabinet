<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operational alerts pushed to Telegram.
 *
 * Each event is a row in `alert_rules`, so the team can switch one off or
 * retune its threshold from the admin panel without a deploy.
 */
enum AlertEvent: string
{
    case DepositLow = 'deposit_low';           // a PSP's prepaid balance fell below the threshold
    case DepositToppedUp = 'deposit_topped_up'; // a PSP credited their deposit
    case PaymentReceived = 'payment_received';  // tuition paid by a student
    case DailySummary = 'daily_summary';        // yesterday's volume + count
    case UserCreated = 'user_created';           // a cabinet account was opened
    case PasswordReset = 'password_reset';       // an account's password was revoked and reissued

    public function label(): string
    {
        return match ($this) {
            self::DepositLow => 'Low PSP deposit',
            self::DepositToppedUp => 'Deposit topped up',
            self::PaymentReceived => 'Tuition payment received',
            self::DailySummary => 'Daily summary',
            self::UserCreated => 'Cabinet user created',
            self::PasswordReset => 'Password reset',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DepositLow => 'Fires when a PSP deposit drops below the threshold after a payment is debited.',
            self::DepositToppedUp => 'Fires when a PSP adds funds to their prepaid deposit.',
            self::PaymentReceived => 'Fires on every completed tuition payment. Use the minimum amount to keep it quiet.',
            self::DailySummary => 'Sent once a day with the previous day\'s total volume, commission and transaction count.',
            self::UserCreated => 'Fires when an admin opens a merchant, partner or admin account. The temporary password is never included.',
            self::PasswordReset => 'Fires when an admin revokes an account\'s password. Notice that it happened, not the new password.',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::DepositLow => '🔴',
            self::DepositToppedUp => '💰',
            self::PaymentReceived => '✅',
            self::DailySummary => '📊',
            self::UserCreated => '👤',
            self::PasswordReset => '🔑',
        };
    }

    /** Whether a tiyin threshold applies to this event. */
    public function usesThreshold(): bool
    {
        return in_array($this, [self::DepositLow, self::PaymentReceived], true);
    }
}
