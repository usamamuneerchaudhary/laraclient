<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The connection used when you call LaraClient methods without naming one,
    | e.g. LaraClient::get('users') instead of
    | LaraClient::connection('github')->get('users').
    |
    */

    'default' => env('LARACLIENT_CONNECTION', 'example'),

    /*
    |--------------------------------------------------------------------------
    | Log Database Connection
    |--------------------------------------------------------------------------
    |
    | Where laraclient_logs lives. Leave null to use the application default;
    | point it at a separate connection to keep high-volume request logs off
    | your primary database.
    |
    */

    'database_connection' => env('LARACLIENT_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Global Defaults
    |--------------------------------------------------------------------------
    |
    | Every connection inherits these and may override any key. This is where
    | you set org-wide policy once instead of repeating it per integration.
    |
    */

    'defaults' => [

        'timeout' => 30,
        'connect_timeout' => 5,

        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],

        // Retry failed requests with exponential backoff plus jitter.
        'retry' => [
            'enabled' => true,
            'times' => 3,
            'base_delay' => 200,      // milliseconds before the first retry
            'multiplier' => 2.0,      // 200ms -> 400ms -> 800ms
            'max_delay' => 10_000,
            'jitter' => true,         // spreads retries so clients don't sync up
            'statuses' => [408, 425, 429, 500, 502, 503, 504],
            'methods' => ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'],
            'retry_on_connection_error' => true,
            'respect_retry_after' => true,
        ],

        // Client-side throttle. Keeps you under the provider's published limit
        // instead of discovering it via 429s.
        'rate_limit' => [
            'enabled' => false,
            'limit' => 60,            // requests per window
            'window' => 60,           // window length in seconds
            // 'throw' returns control to you (queue jobs can release themselves);
            // 'wait' sleeps until the window rolls over.
            'on_limit' => 'throw',
            'max_wait' => 10,         // seconds, only used when on_limit = wait
        ],

        // Stop hammering an upstream that is already down.
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5, // consecutive failures before opening
            'cooldown' => 60,         // seconds the circuit stays open
            'sample_statuses' => [500, 502, 503, 504],
        ],

        // Response caching. GET only by default.
        'cache' => [
            'enabled' => false,
            'ttl' => 300,
            'store' => null,          // null = your app's default cache store
            'methods' => ['GET', 'HEAD'],
            'respect_etag' => true,   // revalidate with If-None-Match on expiry
            'tag' => null,            // defaults to "laraclient:{connection}"
        ],

        // Request/response logging to the laraclient_logs table.
        'logging' => [
            'enabled' => true,
            'store_request_body' => true,
            'store_response_body' => true,
            'max_body_length' => 64_000,
            'only_failures' => false, // set true in production to log less
        ],

        // Pagination strategy, so ->paginate() knows how to walk the API.
        'pagination' => [
            'strategy' => 'page',     // page | offset | cursor | link_header
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'per_page' => 100,
            'data_key' => 'data',
            'cursor_param' => 'cursor',
            'next_cursor_key' => 'meta.next_cursor',
            'total_key' => 'meta.total',
        ],

        'idempotency' => [
            'enabled' => false,
            'header' => 'Idempotency-Key',
            'methods' => ['POST', 'PATCH'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each entry is one third-party API. Anything you omit falls back to the
    | 'defaults' block above.
    |
    */

    'connections' => [

        'example' => [
            'base_uri' => env('EXAMPLE_BASE_URI', 'https://api.example.com/'),
            'auth' => [
                'driver' => 'bearer',
                'token' => env('EXAMPLE_TOKEN'),
            ],
        ],

        // Header auth, e.g. RapidAPI.
        'weatherapi' => [
            'base_uri' => 'https://weatherapi-com.p.rapidapi.com/',
            'auth' => [
                'driver' => 'header',
                'name' => 'X-RapidAPI-Key',
                'value' => env('RAPIDAPI_KEY'),
            ],
            'headers' => [
                'X-RapidAPI-Host' => 'weatherapi-com.p.rapidapi.com',
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 600,
            ],
        ],

        // OAuth2 client credentials. Tokens are fetched, cached and refreshed
        // for you; nothing to wire up in your app code.
        'salesforce' => [
            'base_uri' => env('SALESFORCE_BASE_URI', 'https://example.my.salesforce.com/services/data/v60.0/'),
            'auth' => [
                'driver' => 'oauth2',
                'token_url' => env('SALESFORCE_TOKEN_URL'),
                'client_id' => env('SALESFORCE_CLIENT_ID'),
                'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
                'scopes' => [],
                'leeway' => 60,       // refresh this many seconds before expiry
            ],
            'rate_limit' => [
                'enabled' => true,
                'limit' => 100,
                'window' => 60,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Header names and body keys masked before anything is written to the logs
    | table or the dashboard. Matching is case-insensitive. Add your own keys
    | here; the defaults only cover the obvious ones.
    |
    */

    'redact' => [
        'headers' => [
            'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
            'x-api-key', 'x-rapidapi-key', 'x-auth-token', 'api-key',
        ],
        'body' => [
            'password', 'password_confirmation', 'secret', 'token',
            'access_token', 'refresh_token', 'client_secret', 'api_key',
            'card_number', 'cvv', 'ssn',
        ],
        'replacement' => '[redacted]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Dashboard
    |--------------------------------------------------------------------------
    |
    | The dashboard exposes request and response bodies, so it is gated. The
    | 'viewLaraClient' gate is checked in addition to this middleware stack.
    |
    */

    'dashboard' => [
        'enabled' => env('LARACLIENT_DASHBOARD', true),
        'path' => 'laraclient',
        'middleware' => ['web', 'auth'],
        'per_page' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan laraclient:prune`. Schedule it daily.
    |
    */

    'prune' => [
        'keep_days' => 14,
        'keep_failures_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording (VCR)
    |--------------------------------------------------------------------------
    |
    | 'record' writes every real response to a fixture. 'replay' serves those
    | fixtures instead of making network calls, which makes CI hermetic.
    |
    */

    'recorder' => [
        'mode' => env('LARACLIENT_RECORDER', 'off'), // off | record | replay
        'path' => 'tests/fixtures/laraclient',
        'match_on' => ['method', 'uri', 'body'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    */

    'telemetry' => [
        'pulse' => true,          // register the Pulse card if Pulse is installed
        'opentelemetry' => false, // emit a span per request if the SDK is present
    ],
];
