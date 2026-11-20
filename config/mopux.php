<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mopux installation
    |--------------------------------------------------------------------------
    |
    | URL of your Mopux installation (e.g. https://acme.mopux.com) and an API
    | token: a personal access token (`id|secret`, obtained with the login
    | endpoint) or an OAuth access token with the mopux:read/mopux:write scopes.
    |
    */

    'url' => env('MOPUX_URL'),

    'token' => env('MOPUX_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Timeouts in seconds. The user agent is appended to the SDK's own one.
    |
    */

    'timeout' => (float) env('MOPUX_TIMEOUT', 30),

    'connect_timeout' => (float) env('MOPUX_CONNECT_TIMEOUT', 10),

    'user_agent' => env('MOPUX_USER_AGENT', env('APP_NAME')),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | 429 responses are retried for every method honouring Retry-After; network
    | errors and 5xx only for reads. Writes are never retried after an uncertain
    | failure. Set max_retries to 0 to disable retries.
    |
    */

    'retry' => [
        'max_retries' => (int) env('MOPUX_MAX_RETRIES', 2),
        'base_delay_ms' => 250,
        'max_delay_ms' => 5000,
        'max_retry_after_seconds' => 60,
    ],

];
