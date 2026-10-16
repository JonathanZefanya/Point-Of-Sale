<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Dipisah koma, mis. "http://localhost:5173,http://192.168.1.10:5173"
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    // Autentikasi memakai token Bearer, bukan cookie
    'supports_credentials' => false,

];
