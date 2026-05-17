<?php

return [
    'api' => [
        'base_url' => env('OMR_API_BASE_URL', ''),
        'timeout_seconds' => (int) env('OMR_API_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('OMR_API_CONNECT_TIMEOUT_SECONDS', 5),
        'retry_times' => (int) env('OMR_API_RETRY_TIMES', 0),
        'retry_sleep_ms' => (int) env('OMR_API_RETRY_SLEEP_MS', 250),
        'bearer_token' => env('OMR_API_BEARER_TOKEN', ''),
        'paths' => [
            'entrance' => env('OMR_API_ENTRANCE_PATH', '/scan/bubbles'),
            'term' => env('OMR_API_TERM_PATH', '/scan/bubbles'),
        ],
    ],
];
