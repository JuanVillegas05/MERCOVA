<?php

use App\Http\Middleware\EnforcePlanQuota;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'plan.quota' => EnforcePlanQuota::class,
            'plan.feature' => EnsurePlanFeature::class,
        ]);

        // Apple devuelve el login con un POST desde appleid.apple.com, que no
        // puede traer nuestro token CSRF. Ese callback se protege con el
        // nonce del id_token (ver SocialAuthController).
        $middleware->validateCsrfTokens(except: ['auth/apple/callback']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
