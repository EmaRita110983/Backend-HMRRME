<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        '*',
    ],

    // En local, el hardcode a :5173 alcanza. En producción el frontend vive
    // en otro dominio (ej. Vercel) y sin agregarlo acá el navegador bloquea
    // toda petición desde ahí con un error de CORS, aunque el backend esté
    // funcionando perfectamente — CORS_ALLOWED_ORIGINS permite sumar esos
    // dominios reales por variable de entorno sin tocar código.
    'allowed_origins' => array_values(array_filter(array_merge(
        ['http://localhost:5173'],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    // Permite abrir el frontend desde otros dispositivos en la misma red local
    // (ej. celular) durante desarrollo, sin tener que hardcodear la IP de la máquina.
    'allowed_origins_patterns' => [
        '#^http://192\.168\.\d{1,3}\.\d{1,3}:5173$#',
        '#^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:5173$#',
        '#^http://172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}:5173$#',
        // Túneles temporales de Cloudflare (cloudflared tunnel --url) para probar desde afuera de la red local.
        '#^https://.*\.trycloudflare\.com$#',
    ],

    'allowed_headers' => [
        '*',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];