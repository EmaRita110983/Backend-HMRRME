<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        '*',
    ],

    'allowed_origins' => [
        'http://localhost:5173',
    ],

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