<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function insufficientDeposit(): self
    {
        return new self('insufficient_deposit', 'PSP deposit balance is insufficient for this payment.', 402);
    }

    public static function checkExpired(): self
    {
        return new self('check_expired', 'The payment check has expired or does not exist.', 410);
    }

    public static function institutionInactive(): self
    {
        return new self('institution_inactive', 'The institution is not active.', 422);
    }
}
