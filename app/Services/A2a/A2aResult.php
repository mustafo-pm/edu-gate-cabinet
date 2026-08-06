<?php

declare(strict_types=1);

namespace App\Services\A2a;

use App\Enums\BankTransferStatus;

/**
 * What a bank told us about one payment order.
 *
 * The critical field is `status`. `Unknown` is not a failure — it means we never
 * learned whether the money left (a timeout, or one of the bank's own
 * "check the status, do not retry" codes). Treating it as a failure and
 * resending is how an institution gets paid twice.
 */
final class A2aResult
{
    public function __construct(
        public readonly BankTransferStatus $status,
        public readonly ?string $externalId = null,
        public readonly ?int $code = null,
        public readonly ?string $message = null,
        public readonly ?array $raw = null,
        /** Exactly what we put on the wire — shown verbatim in the register. */
        public readonly ?array $request = null,
    ) {}

    public static function accepted(?string $externalId, ?array $raw = null, ?array $request = null): self
    {
        return new self(BankTransferStatus::Sent, $externalId, 0, null, $raw, $request);
    }

    public static function settled(?string $externalId, ?array $raw = null, ?array $request = null): self
    {
        return new self(BankTransferStatus::Confirmed, $externalId, 0, null, $raw, $request);
    }

    public static function rejected(?int $code, ?string $message, ?array $raw = null, ?string $externalId = null, ?array $request = null): self
    {
        return new self(BankTransferStatus::Failed, $externalId, $code, $message, $raw, $request);
    }

    /** No answer, or an answer that means "the order may exist — go and ask". */
    public static function unknown(?string $message, ?array $raw = null, ?int $code = null, ?array $request = null): self
    {
        return new self(BankTransferStatus::Unknown, null, $code, $message, $raw, $request);
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
