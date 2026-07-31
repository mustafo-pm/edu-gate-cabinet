<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function (): void {
            // Merchant cabinet — app.edu-gate.uz (dev: /merchant/*)
            Route::middleware('web')
                ->prefix('merchant')
                ->name('merchant.')
                ->group(base_path('routes/merchant.php'));

            // PSP / Partner cabinet — partner.edu-gate.uz (dev: /partner/*)
            Route::middleware('web')
                ->prefix('partner')
                ->name('psp.')
                ->group(base_path('routes/psp.php'));
            // (admin.edu-gate.uz is served by the Filament panel at /admin)
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply the user's chosen locale on every web request.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
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
        $exceptions->render(function (\App\Exceptions\PaymentException $e) {
            return response()->json([
                'status' => 'error',
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], $e->status);
        });
    })->create();
