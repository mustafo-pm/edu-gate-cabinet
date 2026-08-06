<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApiIndexController;
use App\Http\Controllers\Auth\UnifiedLoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The cabinet and the API are one Laravel application. When api.edu-gate.uz is
 * pointed at the same public/ directory, every unconstrained route answers
 * there too — including the Filament admin panel. These pin down that the
 * groups can be separated, and that leaving the config empty keeps the
 * single-host development setup working.
 */
it('serves the API on any host while no API_DOMAIN is set', function () {
    // The development default: told apart by path, not by hostname.
    expect(config('domains.api'))->toBeNull();

    $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
});

it('registers the API without a host constraint by default', function () {
    $route = collect(Route::getRoutes())->first(
        fn ($r) => $r->uri() === 'api/v1/auth/login',
    );

    expect($route)->not->toBeNull()
        ->and($route->getDomain())->toBeNull();
});

it('pins a group to a host when the domain is configured', function () {
    // Registration happens at boot, so exercise the same construction the
    // routing file uses rather than re-booting the application.
    $group = fn (?string $domain, string $middleware) => filled($domain)
        ? Route::middleware($middleware)->domain($domain)
        : Route::middleware($middleware);

    $group('api.edu-gate.uz', 'api')->get('_probe_pinned', fn () => 'ok');
    $group(null, 'api')->get('_probe_open', fn () => 'ok');

    $routes = collect(Route::getRoutes()->getRoutes());

    expect($routes->first(fn ($r) => $r->uri() === '_probe_pinned')->getDomain())
        ->toBe('api.edu-gate.uz')
        ->and($routes->first(fn ($r) => $r->uri() === '_probe_open')->getDomain())
        ->toBeNull();
});

it('hides a host-pinned route from the wrong host', function () {
    config(['domains.admin' => 'admin.edu-gate.uz']);

    Route::middleware('host:admin')->get('_probe_admin', fn () => 'ok');

    // 404 rather than 403: a wrong host should not confirm the panel exists.
    $this->get('http://cabinet.edu-gate.uz/_probe_admin')->assertNotFound();
    $this->get('http://admin.edu-gate.uz/_probe_admin')->assertOk();
});

it('lets every host through when the key is unset', function () {
    config(['domains.admin' => null]);

    Route::middleware('host:admin')->get('_probe_open_admin', fn () => 'ok');

    $this->get('http://anything.test/_probe_open_admin')->assertOk();
});

it('returns a JSON pointer rather than documentation at the API root', function () {
    // Serving docs from the API host would answer 200 during an API outage.
    $response = app(ApiIndexController::class)();

    expect($response->getData(true))
        ->toHaveKey('status', 'ok')
        ->and($response->getData(true)['data']['base_path'])->toBe('/api/v1');
});

/**
 * Regression: the API root was briefly registered unconstrained and LAST, on
 * the assumption it would sit behind the cabinet's "/". Laravel keys routes by
 * domain+URI, so it silently REPLACED the login page instead — the cabinet root
 * started returning JSON.
 */
it('still serves the login page at / while no host is pinned', function () {
    expect(config('domains.cabinet'))->toBeNull()
        ->and(config('domains.api'))->toBeNull();

    $route = Route::getRoutes()->match(
        Request::create('http://localhost/', 'GET'),
    );

    expect($route->getActionName())
        ->toBe(UnifiedLoginController::class.'@show');
});

it('does not register the API root until a host is pinned', function () {
    $roots = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => $r->uri() === '/')
        ->map(fn ($r) => $r->getActionName());

    // One route at "/", and it is the cabinet's.
    expect($roots)->toHaveCount(1)
        ->and($roots->first())->not->toContain('ApiIndexController');
});
