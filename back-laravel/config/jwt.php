<?php

return [
    'secret' => env('JWT_SECRET') ?: (env('APP_KEY') ?: 'default-fallback-jwt-secret-key-at-least-32-bytes!'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://api.localhost')),
    'audience' => env('JWT_AUDIENCE', env('APP_URL', 'http://api.localhost')),
    'access_expires_in' => (int) env('JWT_ACCESS_EXPIRES_IN', 900), // 15 minutos
    'refresh_expires_in' => (int) env('JWT_REFRESH_EXPIRES_IN', 604800), // 7 días
    'grace_period_seconds' => (int) env('JWT_GRACE_PERIOD_SECONDS', 20), // 20 segundos
];
