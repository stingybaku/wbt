<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Laravel registers HandleCors in the global middleware stack. Without this
    | file the framework default applied, which is allowed_origins => ['*'] for
    | every api/* path, so API responses were readable by any origin.
    |
    | FRONTEND_URL may hold a comma-separated list when more than one origin
    | needs access. env() is used here rather than in middleware because config
    | files are the one place it stays correct under `php artisan config:cache`.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URL', 'http://localhost:5173,http://127.0.0.1:5173'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 600,

    // The SPA authenticates with a Bearer token, not cookies, so credentialed
    // cross-origin requests are not needed.
    'supports_credentials' => false,

];
