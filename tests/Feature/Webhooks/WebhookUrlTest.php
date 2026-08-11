<?php

declare(strict_types=1);

use App\Support\WebhookUrl;

/**
 * The SSRF guard.
 *
 * This is the only place in the platform where a tenant chooses a host our
 * server will make a request to. Every case below is a way someone could point
 * us at something we are not supposed to reach.
 */
it('accepts an ordinary https endpoint', function () {
    expect(WebhookUrl::reject('https://clickpay.uz/edugate/webhook', resolveDns: false))->toBeNull();
});

it('refuses plain http', function () {
    // The payload carries student names and amounts, and http would put them
    // on the wire in clear.
    expect(WebhookUrl::reject('http://clickpay.uz/hook', resolveDns: false))->toBe('https_required');
});

it('refuses anything that is not a url', function () {
    expect(WebhookUrl::reject('not a url', resolveDns: false))->toBe('invalid')
        ->and(WebhookUrl::reject('ftp://example.com', resolveDns: false))->toBe('https_required')
        // No host at all, so it never reaches the scheme check — rejected all
        // the same, which is the part that matters.
        ->and(WebhookUrl::reject('file:///etc/passwd', resolveDns: false))->toBe('invalid');
});

it('refuses loopback', function () {
    // The IPv6 case also pins the bracket handling: parse_url hands back
    // "[::1]", and unless the brackets are stripped this falls through to DNS
    // and gets refused as "unresolvable" instead — safe by accident, which is
    // not a property to rely on in an SSRF guard.
    expect(WebhookUrl::reject('https://127.0.0.1/hook'))->toBe('private_address')
        ->and(WebhookUrl::reject('https://[::1]/hook'))->toBe('private_address');
});

it('refuses private network ranges', function () {
    foreach (['10.0.0.5', '172.16.4.9', '192.168.1.1'] as $ip) {
        expect(WebhookUrl::reject("https://{$ip}/hook"))->toBe('private_address');
    }
});

it('refuses the cloud metadata address', function () {
    // 169.254.169.254 is where AWS/GCP hand out instance credentials. A tenant
    // who could make us fetch it, and then read our delivery log, would be
    // holding our keys.
    expect(WebhookUrl::reject('https://169.254.169.254/latest/meta-data/'))->toBe('private_address');
});

it('refuses odd ports', function () {
    // Otherwise the endpoint becomes a port scanner: the response code and
    // timing in the delivery log say what is listening.
    expect(WebhookUrl::reject('https://clickpay.uz:6379/hook', resolveDns: false))->toBe('port_not_allowed')
        ->and(WebhookUrl::reject('https://clickpay.uz:8443/hook', resolveDns: false))->toBeNull();
});

it('refuses a hostname that does not resolve', function () {
    expect(WebhookUrl::reject('https://this-host-does-not-exist-'.md5('eg').'.invalid/hook'))
        ->toBe('unresolvable');
});

it('refuses a hostname that resolves to a private address', function () {
    // localtest.me and friends resolve to 127.0.0.1 — the shape of a DNS
    // rebinding attack, where a public-looking name points somewhere private.
    $reason = WebhookUrl::reject('https://localhost/hook');

    expect($reason)->toBe('private_address');
});
