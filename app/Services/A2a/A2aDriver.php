<?php

declare(strict_types=1);

namespace App\Services\A2a;

use App\Models\BankTransfer;

/**
 * One bank's account-to-account integration.
 *
 * Implementations differ wildly — Aloqabank is REST with HTTP Basic, while
 * Universalbank is JSON-RPC — so nothing about the wire format belongs above
 * this interface. What every bank shares is: push an order, then ask what
 * happened to it.
 *
 * Implementations must never throw for a bank-side failure; they map it onto an
 * A2aResult instead. Only a programming error (bad config, missing account)
 * should raise.
 */
interface A2aDriver
{
    /** Push the order. A bank that settles asynchronously returns `Sent`. */
    public function send(BankTransfer $transfer): A2aResult;

    /** Ask what became of an order we already pushed. */
    public function status(BankTransfer $transfer): A2aResult;

    /** Key used in `banks.a2a_driver` and `settlement_accounts.driver`. */
    public function key(): string;
}
