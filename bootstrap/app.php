<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en el proxy/tunel de entrada para leer X-Forwarded-Proto: sin esto,
        // detras de un tunel HTTPS (Cloudflare Tunnel, ngrok, etc.) Laravel genera los
        // assets con http:// y el navegador los bloquea por contenido mixto.
        $middleware->trustProxies(at: '*');

        // Límite de tasa en /api/*: el limiter 'api' se define en AppServiceProvider.
        $middleware->throttleApi();

        $middleware->web(append: [
            \App\Http\Middleware\ExpirarSesionInactiva::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias para autorizacion por permiso: ->middleware('permiso:codigo')
        $middleware->alias([
            'permiso' => \App\Http\Middleware\VerificarPermiso::class,
        ]);

        // Redirecciones de autenticacion:
        // - invitados que tocan una ruta protegida -> login (/acceder).
        // - usuarios ya autenticados que tocan rutas de invitado -> panel (/admin).
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/admin');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
