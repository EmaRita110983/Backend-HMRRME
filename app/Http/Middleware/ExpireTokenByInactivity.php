<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

// Corre antes de "auth:sanctum" (prepended en bootstrap/app.php) a propósito:
// Sanctum actualiza last_used_at al mismo momento en que autentica la
// request, así que si este chequeo corriera después ya no podría distinguir
// "recién usado ahora" de "usado hace media hora". Revisando el token acá,
// con findToken() (que no lo autentica ni toca last_used_at), todavía vemos
// el valor de la última vez que se usó de verdad.
class ExpireTokenByInactivity
{
    private const MINUTOS_INACTIVIDAD = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken) {
            $accessToken = PersonalAccessToken::findToken($plainTextToken);

            if ($accessToken
                && $accessToken->last_used_at
                && $accessToken->last_used_at->lt(now()->subMinutes(self::MINUTOS_INACTIVIDAD))
            ) {
                $accessToken->delete();

                return response()->json([
                    'message' => 'Sesión expirada por inactividad.',
                ], 401);
            }
        }

        return $next($request);
    }
}
