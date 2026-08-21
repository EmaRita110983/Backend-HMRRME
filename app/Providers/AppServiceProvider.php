<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Límite general de la API (aparte del RateLimiter propio de
        // funLogin): por usuario autenticado si hay token válido, si no por
        // IP. 200/min es holgado para el uso normal de la SPA (varias
        // peticiones en paralelo al cargar una pantalla) pero corta un
        // abuso automatizado, ej. enumerar cédulas contra
        // users|patients/eliminados/buscar.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip());
        });

        // Sin esto, migrar de SQLite (sin límite práctico de longitud de
        // índice) a MySQL con la config por defecto de un hosting genérico
        // (utf8mb4, sin innodb_large_prefix) rompe cualquier migración con
        // string()->unique() ("Specified key too long"): un varchar(255) en
        // utf8mb4 pesa 1020 bytes, por encima del límite de 767. No afecta a
        // SQLite/Postgres, así que es seguro dejarlo siempre activo.
        Schema::defaultStringLength(191);
    }
}
