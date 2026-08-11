<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Guards a webhook address supplied by a PSP.
 *
 * This is the one place in the platform where a tenant tells our server which
 * host to make a request to. Left open, a PSP could point us at 127.0.0.1, at
 * a database on the private network, or at a cloud metadata service, and read
 * the answer through the delivery log — turning us into their way in. Every
 * check below exists for that, not for tidiness.
 *
 * Two rules that are easy to get wrong:
 *
 *  • Validation at save time is not enough. A hostname that resolved to a
 *    public address when it was saved can be re-pointed at 127.0.0.1 an hour
 *    later, so DeliverWebhook re-checks immediately before it sends.
 *
 *  • Every resolved address must pass. A hostname can return several A records,
 *    and accepting the first public one lets a private address ride along.
 */
final class WebhookUrl
{
    /**
     * How hostnames are resolved. Swapped in tests.
     *
     * A seam rather than a "skip DNS in testing" flag on purpose: turning the
     * lookup off under test would leave the most important half of this class
     * unexercised, which is the opposite of what the tests are for. With a
     * stub, both the allowed and the blocked path can be proven without a
     * network.
     *
     * @var (\Closure(string): array<int, string>)|null
     */
    private static ?\Closure $resolver = null;

    /** @param  (\Closure(string): array<int, string>)|null  $resolver */
    public static function resolveUsing(?\Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Why this URL cannot be used, or null if it is fine.
     *
     * Returns a reason rather than throwing because both callers want to show
     * it: the cabinet as a validation message, the job as a delivery error.
     */
    public static function reject(string $url, bool $resolveDns = true): ?string
    {
        $parts = parse_url($url);

        if (! $parts || ! isset($parts['host'], $parts['scheme'])) {
            return 'invalid';
        }

        // Plain HTTP would put the payload — student names, amounts — on the
        // wire in clear, and would let anyone on the path forge our signature
        // header's context.
        if (strtolower($parts['scheme']) !== 'https') {
            return 'https_required';
        }

        if (isset($parts['port']) && ! in_array($parts['port'], [443, 8443], true)) {
            return 'port_not_allowed';
        }

        // parse_url returns an IPv6 literal wrapped in brackets ("[::1]"), and
        // those brackets make filter_var reject it as an IP — which would send
        // every IPv6 address down the DNS path instead of being checked here.
        $host = trim($parts['host'], '[]');

        // A bare IP skips DNS entirely, so check it directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::addressAllowed($host) ? null : 'private_address';
        }

        if (! $resolveDns) {
            return null;
        }

        $addresses = self::resolve($host);

        if ($addresses === []) {
            return 'unresolvable';
        }

        foreach ($addresses as $address) {
            if (! self::addressAllowed($address)) {
                return 'private_address';
            }
        }

        return null;
    }

    public static function allowed(string $url, bool $resolveDns = true): bool
    {
        return self::reject($url, $resolveDns) === null;
    }

    /** @return array<int, string> every A/AAAA record, not just the first */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null,
            $records,
        )));
    }

    /**
     * Public, routable addresses only.
     *
     * FILTER_FLAG_NO_PRIV_RANGE covers 10/8, 172.16/12, 192.168/16 and fc00::/7;
     * NO_RES_RANGE covers loopback, 0.0.0.0/8, and 169.254/16 — the last being
     * the link-local range cloud metadata services sit on.
     */
    private static function addressAllowed(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
