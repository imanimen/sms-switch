<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Operating mode
    |--------------------------------------------------------------------------
    |
    | manual  : always send via `default_provider`. No fallback on failure.
    | auto    : score-based selection with circuit breaker. Falls through to
    |           the next healthy provider if the primary fails.
    */

    'mode' => env('SMS_SWITCH_MODE', 'manual'),

    'default_provider' => env('SMS_SWITCH_DEFAULT', 'smsir'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | `weight` is a manual bias applied to the score (0-100). Use it to
    | favor a cheaper or preferred provider when success rates are equal.
    */

    'providers' => [

        'smsir' => [
            'driver'      => 'smsir',
            'api_key'     => env('SMSIR_API_KEY'),
            'line_number' => env('SMSIR_LINE'),
            'base_url'    => env('SMSIR_BASE_URL', 'https://api.sms.ir'),
            'weight'      => 100,
            'enabled'     => (bool) env('SMSIR_ENABLED', true),
        ],

        'kavenegar' => [
            'driver'   => 'kavenegar',
            'api_key'  => env('KAVENEGAR_API_KEY'),
            'sender'   => env('KAVENEGAR_SENDER'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com'),
            'weight'   => 80,
            'enabled'  => (bool) env('KAVENEGAR_ENABLED', true),
        ],

        'melipayamak' => [
            'driver'   => 'melipayamak',
            'username' => env('MELIPAYAMAK_USERNAME'),
            'password' => env('MELIPAYAMAK_PASSWORD'),
            'from'     => env('MELIPAYAMAK_FROM'),
            'base_url' => env('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com'),
            'weight'   => 60,
            'enabled'  => (bool) env('MELIPAYAMAK_ENABLED', true),
        ],

        'mobinsms' => [
            'driver'   => 'mobinsms',
            'api_key'  => env('MOBINSMS_API_KEY'),
            'from'     => env('MOBINSMS_FROM'),
            'base_url' => env('MOBINSMS_BASE_URL'),
            'weight'   => 40,
            'enabled'  => (bool) env('MOBINSMS_ENABLED', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'failure_threshold'           => (int) env('SMS_CB_THRESHOLD', 5),
        'open_duration_seconds'       => (int) env('SMS_CB_OPEN_SECONDS', 60),
        'half_open_success_threshold' => (int) env('SMS_CB_HALFOPEN_SUCCESS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring (used only in auto mode)
    |--------------------------------------------------------------------------
    */

    'scoring' => [
        'weights' => [
            'success_rate'  => 0.6,
            'latency'       => 0.3,
            'manual_weight' => 0.1,
        ],
        'window_minutes'          => (int) env('SMS_SCORING_WINDOW_MIN', 15),
        'max_expected_latency_ms' => (int) env('SMS_MAX_LATENCY_MS', 3000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & retention
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled'        => (bool) env('SMS_LOGGING_ENABLED', true),
        'retention_days' => (int) env('SMS_LOG_RETENTION_DAYS', 30),
        'connection'     => env('SMS_LOG_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client defaults (applied to every provider adapter)
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout'         => (int) env('SMS_HTTP_TIMEOUT', 5),
        'connect_timeout' => (int) env('SMS_HTTP_CONNECT_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue (used by Sms::queue / Sms::queuePattern)
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('SMS_QUEUE_CONNECTION'),
        'queue'      => env('SMS_QUEUE_NAME', 'sms'),
    ],

];
