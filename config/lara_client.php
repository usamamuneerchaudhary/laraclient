<?php

return [
    'default' => env('API_CLIENT', 'api'),

    'connections' => [
        'api' => [
            'base_uri' => env('API_BASE_URI', 'https://api.example.com/'),
            'api_key' => env('API_API_KEY', ''),
            'default_headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'api_secret' => env('API_API_SECRET', ''),
            'timeout' => env('API_TIMEOUT', 30),
            'rate_limit' => [
                'limit' => env('API_RATE_LIMIT', 60),
                'interval' => env('API_RATE_LIMIT_INTERVAL', 60),
            ],
        ],
        'otherapi' => [
            // OtherApi configuration
        ],
    ],
];
