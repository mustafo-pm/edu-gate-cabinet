<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AlertEvent;
use App\Jobs\SendAlert;
use App\Models\AlertRule;

/**
 * Entry point for raising an operational alert.
 *
 * Callers use Alerts::raise(...) and never touch Telegram directly: the rule
 * is checked here, and delivery is deferred to a job that only runs once the
 * surrounding database transaction has committed.
 */
final class Alerts
{
    /**
     * @param  array<string, mixed>  $payload  values referenced by the formatter
     */
    public static function raise(AlertEvent $event, array $payload = []): void
    {
        if (! AlertRule::enabled($event)) {
            return;
        }

        // afterCommit means a rolled-back payment never announces itself.
        SendAlert::dispatch($event->value, $payload)->afterCommit();
    }

    /** Build the Telegram HTML body for an event. */
    public static function format(AlertEvent $event, array $p): string
    {
        $e = Telegram::escape(...);

        return match ($event) {
            AlertEvent::DepositLow => implode("\n", array_filter([
                $event->emoji().' <b>Low deposit balance</b>',
                '',
                'Partner: <b>'.$e($p['psp'] ?? '—').'</b>',
                'Balance: <b>'.Money::format((int) ($p['balance'] ?? 0)).'</b>',
                'Threshold: '.Money::format((int) ($p['threshold'] ?? 0)),
                '',
                '<i>Payments will be declined once the balance cannot cover them.</i>',
            ])),

            AlertEvent::DepositToppedUp => implode("\n", array_filter([
                $event->emoji().' <b>Deposit topped up</b>',
                '',
                'Partner: <b>'.$e($p['psp'] ?? '—').'</b>',
                'Amount: <b>+'.Money::format((int) ($p['amount'] ?? 0)).'</b>',
                'New balance: '.Money::format((int) ($p['balance'] ?? 0)),
                isset($p['reference']) ? 'Reference: <code>'.$e($p['reference']).'</code>' : null,
            ])),

            AlertEvent::PaymentReceived => implode("\n", array_filter([
                $event->emoji().' <b>Tuition payment received</b>',
                '',
                'Institution: <b>'.$e($p['merchant'] ?? '—').'</b>',
                isset($p['student']) ? 'Student: '.$e($p['student']) : null,
                'Amount: <b>'.Money::format((int) ($p['amount'] ?? 0)).'</b>',
                'Commission: '.Money::format((int) ($p['commission'] ?? 0)),
                'Via: '.$e($p['psp'] ?? '—'),
                isset($p['reference']) ? 'Reference: <code>'.$e($p['reference']).'</code>' : null,
            ])),

            /*
             * Neither of these carries the password. The Telegram destination
             * is a team group whose history is permanent and searchable, and a
             * credential posted there outlives the account. The temporary
             * password is shown once in the admin screen instead, for whoever
             * is going to hand it over.
             */
            AlertEvent::UserCreated => implode("\n", array_filter([
                $event->emoji().' <b>Cabinet account created</b>',
                '',
                'Name: <b>'.$e($p['name'] ?? '—').'</b>',
                'Email: <code>'.$e($p['email'] ?? '—').'</code>',
                'Cabinet: '.$e($p['cabinet'] ?? '—'),
                isset($p['organisation']) ? 'Organisation: '.$e($p['organisation']) : null,
                isset($p['by']) ? 'Created by: '.$e($p['by']) : null,
                '',
                '<i>A temporary password was issued and must be changed at first sign-in.</i>',
            ])),

            AlertEvent::PasswordReset => implode("\n", array_filter([
                $event->emoji().' <b>Password reset</b>',
                '',
                'Account: <b>'.$e($p['name'] ?? '—').'</b>',
                'Email: <code>'.$e($p['email'] ?? '—').'</code>',
                'Cabinet: '.$e($p['cabinet'] ?? '—'),
                isset($p['by']) ? 'Reset by: '.$e($p['by']) : null,
                '',
                '<i>Any existing session was ended. If this was not expected, treat the account as compromised.</i>',
            ])),

            AlertEvent::DailySummary => implode("\n", array_filter([
                $event->emoji().' <b>Daily summary</b> — '.$e($p['date'] ?? ''),
                '',
                'Payments: <b>'.number_format((int) ($p['count'] ?? 0)).'</b>',
                'Volume: <b>'.Money::format((int) ($p['volume'] ?? 0)).'</b>',
                'Commission: '.Money::format((int) ($p['commission'] ?? 0)),
                'Settled to institutions: '.Money::format((int) ($p['net'] ?? 0)),
                isset($p['top_psp']) ? "\nBusiest partner: <b>".$e($p['top_psp']).'</b>' : null,
            ])),
        };
    }
}
