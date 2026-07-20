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
    public function handle(Request $request, Closure $next, $role): Response
{
    if (!$request->user()) {
        return response()->json([
            'message' => 'Usuario no autenticado'
        ], 401);
    }

    if ($request->user()->role !== $role) {
        return response()->json([
            'message' => 'No tiene permisos para realizar esta acción'
        ], 403);
    }

    return $next($request);
}
}
