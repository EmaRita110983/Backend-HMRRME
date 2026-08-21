<?php

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
    // Limitador 'api' registrado en AppServiceProvider::boot(); sin esto,
    // ninguna ruta de la API tenía rate limiting salvo el propio de
    // AuthController::funLogin.
    $middleware->throttleApi();

    // API pura, sin pantalla de login por sesión: por defecto, cuando una
    // petición no autenticada no manda "Accept: application/json" (ej. un
    // cliente HTTP sin ese header, no el frontend, que sí lo manda siempre
    // vía service/api.js), el middleware "auth" intenta redirigir a una
    // ruta llamada "login" que no existe acá, y eso rompía con un 500
    // ("Route [login] not defined") en vez de devolver un 401 limpio.
    $middleware->redirectGuestsTo(fn () => null);

    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
        // Antes de auth:sanctum a propósito: ver el comentario en la propia
        // clase para por qué el orden importa acá.
        \App\Http\Middleware\ExpireTokenByInactivity::class,
    ]);

    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'credentials.changed' => \App\Http\Middleware\EnsureCredentialsChanged::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
