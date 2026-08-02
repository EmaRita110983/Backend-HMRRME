<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
  public function handle(Request $request, Closure $next, ...$roles): Response
{
    \Log::info([
        'roles_recibidos_array' => $roles
    ]);

    $rolesPermitidos = $roles;

    if (!$request->user()) {
        return response()->json([
            'message' => 'Usuario no autenticado'
        ], 401);
    }

    if (!in_array($request->user()->role, $rolesPermitidos)) {
        return response()->json([
            'message' => 'No tiene permisos para acceder a este recurso.'
        ], 403);
    }

    return $next($request);
}
}
