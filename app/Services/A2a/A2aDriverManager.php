<?php

declare(strict_types=1);

namespace App\Services\A2a;

/**
 * Resolves a driver key (`banks.a2a_driver`, `settlement_accounts.driver`) to
 * the integration that speaks that bank's protocol.
 *
 * Returns null for an unknown key rather than throwing: an unconfigured bank is
 * an operational gap to be surfaced on the posting, not a crash in a queue
 * worker.
 */
class A2aDriverManager
{
    /** @var array<string, class-string<A2aDriver>> */
    private array $drivers = [
        'aloqabank' => AloqabankDriver::class,
    ];

    public function for(?string $key): ?A2aDriver
    {
        if (blank($key) || ! isset($this->drivers[$key])) {
            return null;
        }

        return app($this->drivers[$key]);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->drivers);
    }
}
