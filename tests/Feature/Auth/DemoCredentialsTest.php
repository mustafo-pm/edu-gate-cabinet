<?php

declare(strict_types=1);

/**
 * The seeded-login hint must never reach a public sign-in page.
 *
 * It was previously gated on @production, and the staging cabinet runs with
 * APP_ENV=local so the bank simulator's routes register — which published the
 * hint on cabinet.edu-gate.uz. Whether a host is public and whether it is
 * "local" are different questions.
 */
it('hides the seeded logins unless explicitly switched on', function (string $url) {
    config(['demo.show_credentials' => false]);

    $this->get($url)->assertOk()->assertDontSee('edu-gate.uz · password');
})->with(['/', '/merchant/login', '/partner/login']);

it('still shows them when a developer opts in', function () {
    config(['demo.show_credentials' => true]);

    $this->get('/merchant/login')->assertOk()->assertSee('merchant@edu-gate.uz · password');
});

it('does not key the hint on the environment', function () {
    // APP_ENV=local + a public host is exactly the case that leaked before.
    app()->detectEnvironment(fn () => 'local');
    config(['demo.show_credentials' => false]);

    $this->get('/')->assertOk()->assertDontSee('admin@edu-gate.uz');
});
