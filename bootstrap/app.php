<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── TRUST PROXIES ─────────────────────────────────────────────────────
        // Railway (and most cloud platforms) sit behind a reverse proxy/load
        // balancer. Without this, Laravel sees the internal HTTP request and
        // generates cookies without the Secure flag — the browser then refuses
        // to send them back on HTTPS, causing 419 CSRF failures on login/logout.
        // For production on Railway, uncomment the trustProxies line below.
        // For local development, this is NOT needed and causes HTTPS redirect issues.
        // if (env('APP_ENV') === 'production') {
        //     $middleware->trustProxies(at: '*');
        // }

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhook/paymongo',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            // AddLinkHeadersForPreloadedAssets removed — causes CSS preload warnings
            // on Hostinger's proxy layer and can interfere with CSRF cookie delivery.
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();