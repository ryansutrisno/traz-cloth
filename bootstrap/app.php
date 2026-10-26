<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxiesValue = $_ENV['TRUSTED_PROXIES']
            ?? $_SERVER['TRUSTED_PROXIES']
            ?? getenv('TRUSTED_PROXIES');
        $trustedProxies = trim(is_string($trustedProxiesValue) ? $trustedProxiesValue : '');
        $trustedProxyAddresses = $trustedProxies === ''
            ? null
            : ($trustedProxies === '*'
                ? '*'
                : array_values(array_filter(
                    array_map(
                        static fn (string $proxy): string => trim($proxy),
                        explode(',', $trustedProxies),
                    ),
                    static fn (string $proxy): bool => $proxy !== '',
                )));

        $middleware->trustProxies(at: $trustedProxyAddresses);
        $middleware->alias([
            'admin' => EnsureAdminRole::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin/*') ? route('admin.login') : route('login'));

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['webhook/midtrans', 'webhook/biteship']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
