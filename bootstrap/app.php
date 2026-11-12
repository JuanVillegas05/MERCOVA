<?php

use App\Http\Middleware\EnforcePlanQuota;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\PreventResponseCaching;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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

        // En producción (Render) el TLS termina en su balanceador, que es el
        // único que llega a la app. Sin confiar en él, todos los usuarios
        // comparten su IP (y el throttle de login se agota para todos a la
        // vez) y las URLs se generan con http://, así que Wompi no
        // recibe notification_url.
        $middleware->trustProxies(at: '*');

        // Ninguna respuesta de la API (datos de cuenta, pedidos, tokens...) se
        // guarda en caché del navegador ni de proxies. Va antes que la
        // autenticación y el throttle en la lista de prioridad para que sus
        // 401/429 también lleven las cabeceras.
        $middleware->api(prepend: [PreventResponseCaching::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: PreventResponseCaching::class);

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
