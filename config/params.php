<?php

$origins = array_filter(array_map('trim', explode(',', (string) getenv('AUREUS_CORS_ORIGINS'))));

return [
    'corsOrigins' => $origins ?: [
        'http://localhost:4321',
        'http://localhost:3000',
        'http://127.0.0.1:4321',
    ],
];
