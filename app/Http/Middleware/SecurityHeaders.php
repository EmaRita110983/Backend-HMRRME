<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// El frontend en Vercel ya tiene un set completo de headers de seguridad
// (vercel.json: CSP, X-Frame-Options, Referrer-Policy, etc.) porque sirve
// HTML. La API es JSON puro y no tenía ninguno — de menor impacto que en un
// sitio HTML (un JSON no se "clickjackea" ni se enmarca de la misma forma),
// pero son baratos de agregar y cierran el hueco por completo.
//
// Primero en el pipeline a propósito (ver bootstrap/app.php): como esta
// clase agrega los headers DESPUÉS de llamar a $next(), y el pipeline de
// middleware es tipo "cebolla", ir primero la convierte en la capa más
// externa — así, aunque otro middleware corte la request antes (ej.
// RoleMiddleware con un 403, ExpireTokenByInactivity con un 401), esa
// respuesta igual pasa por acá al volver, y no se queda sin los headers.
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Toda esta API devuelve datos dinámicos y casi siempre sensibles
        // (historias clínicas, tokens, etc.) — no hay ninguna respuesta acá
        // que un proxy intermedio o el propio navegador debería cachear.
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        // Solo si la request ya es HTTPS de verdad (gracias a trustProxies
        // en producción): mandar HSTS sobre HTTP no tiene efecto real —los
        // navegadores lo ignoran— pero evitamos la confusión de todos modos.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
