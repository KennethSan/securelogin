<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register', 'forgot-password', 'reset-password'],
    'allowed_origins' => [
        'http://localhost:3000', 
        'http://localhost:5173', 
        'http://localhost:8080',
        'https://localhost:8443',
        'https://localhost', 
        'http://localhost'
    ],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Essential for session-based auth
];