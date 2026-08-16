<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Bloquea el resto de la API mientras el usuario tenga must_change_password
// pendiente (ver UserController::store): sin esto, un médico recién creado
// con la password genérica podía seguir usando la app con normalidad y el
// cambio de contraseña quedaba como algo puramente opcional en el frontend.
class EnsureCredentialsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->must_change_password) {
            return response()->json([
                'message' => 'Debe cambiar su contraseña antes de continuar.'
            ], 403);
        }

        return $next($request);
    }
}
