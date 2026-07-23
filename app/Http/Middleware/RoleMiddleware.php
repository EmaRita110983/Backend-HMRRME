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
    public function handle(Request $request, Closure $next, $roles): Response
    {
        // Verificar que exista un usuario autenticado
        if (!$request->user()) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // Convertir los roles permitidos en un arreglo
        $rolesPermitidos = explode(',', $roles);

        \Log::info('Middleware roles: '.$roles);

     

        // Verificar si el rol del usuario está permitido
        if (!in_array($request->user()->role, $rolesPermitidos)) {
            return response()->json([
                'message' => 'No tiene permisos para acceder a este recurso.'
            ], 403);
        }

        return $next($request);
    }
}
