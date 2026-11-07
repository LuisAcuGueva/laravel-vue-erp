<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://localhost:5174',
        'http://localhost:5173',
        'http://127.0.0.1',
        'http://api.localhost',
    ],

    'allowed_origins_patterns' => [
        '#^https?://([a-zA-Z0-9-]+\.)*localhost(:[0-9]+)?$#',
    ],

    'allowed_headers' => ['*', 'X-Branch-Id'],

    'exposed_headers' => ['X-New-Access-Token', 'X-New-CSRF-Token', 'X-Branch-Id'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
