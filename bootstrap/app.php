<?php

use App\Exceptions\PaymentException;
use App\Http\Controllers\Api\ApiIndexController;
use App\Http\Middleware\EnforceHost;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Everything is registered here rather than through withRouting's
            // web:/api: shortcuts, because each group has to be pinnable to its
            // own hostname. The cabinet and the API are one application; if
            // api.edu-gate.uz points at the same public/ directory, an
            // unconstrained group answers there too — including the admin panel.
            //
            // Both null in development, where the parts are told apart by path.
            $api = config('domains.api');
            $cabinet = config('domains.cabinet');

            $group = fn (?string $domain, string $middleware) => filled($domain)
                ? Route::middleware($middleware)->domain($domain)
                : Route::middleware($middleware);

            // PSP API — api.edu-gate.uz (dev: any host)
            $group($api, 'api')->prefix('api')->group(base_path('routes/api.php'));

            // Unified login and shared web routes — cabinet.edu-gate.uz
            $group($cabinet, 'web')->group(base_path('routes/web.php'));

            // Merchant cabinet — app.edu-gate.uz (dev: /merchant/*)
            $group($cabinet, 'web')
                ->prefix('merchant')
                ->name('merchant.')
                ->group(base_path('routes/merchant.php'));

            // PSP / Partner cabinet — partner.edu-gate.uz (dev: /partner/*)
            $group($cabinet, 'web')
                ->prefix('partner')
                ->name('psp.')
                ->group(base_path('routes/psp.php'));
            // (the Filament admin panel pins itself with EnforceHost:admin)

            // Bank API simulators — stand-ins for a bank we cannot reach yet.
            // Never registered in production; see config/simulator.php.
            // Kept on the cabinet host, which is where ALOQABANK_BASE_URL points.
            if (config('simulator.aloqabank.enabled')) {
                $group($cabinet, 'api')->group(base_path('routes/simulator.php'));
            }

            // The API root, registered LAST and only once some host is pinned.
            //
            // Laravel keys routes by domain+URI, so an UNCONSTRAINED "/" here
            // would silently overwrite the cabinet's login page rather than sit
            // behind it. Registering it whenever either host is set keeps the
            // keys distinct, and means api.edu-gate.uz answers JSON from the
            // moment its document root is repointed — no window where the API
            // host is live but serving the wrong thing, and no need to set
            // API_DOMAIN in the same breath.
            if (filled($api) || filled($cabinet)) {
                $group($api, 'api')->get('/', ApiIndexController::class);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply the user's chosen locale on every web request.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // EnforceHost must beat the auth check.
        //
        // Laravel sorts route middleware by its priority list, and
        // Illuminate\Auth\Middleware\Authenticate is in it — Filament's own
        // Authenticate extends that class, and the sorter walks parents, so it
        // matches too. Middleware NOT in the list keeps its relative position,
        // which let Authenticate be hoisted in front of EnforceHost: a
        // wrong-host request to /admin answered 302 to the login page,
        // confirming both that the panel exists and where it lives. Precisely
        // what the 404 was there to prevent.
        $middleware->prependToPriorityList(
            // The list keys auth by the INTERFACE, not the concrete class.
            // Naming Illuminate\Auth\Middleware\Authenticate here matches
            // nothing, and prependToPriorityList silently appends to the end
            // instead — which is exactly where EnforceHost was already sitting.
            before: AuthenticatesRequests::class,
            prepend: EnforceHost::class,
        );

        $middleware->alias([
            'host' => EnforceHost::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // All unauthenticated web users funnel to the single unified login.
        // API requests still receive a 401 JSON response automatically.
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Domain payment failures → API error envelope.
        $exceptions->render(function (PaymentException $e) {
            return response()->json([
                'status' => 'error',
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], $e->status);
        });
    })->create();
