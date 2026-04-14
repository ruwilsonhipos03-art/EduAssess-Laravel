<?php

return [
    'api' => [
        'base_url' => env('OMR_API_BASE_URL', ''),
        'timeout_seconds' => (int) env('OMR_API_TIMEOUT_SECONDS', 30),
        'bearer_token' => env('OMR_API_BEARER_TOKEN', ''),
        'paths' => [
            'entrance' => env('OMR_API_ENTRANCE_PATH', '/api/omr/check-exam'),
            'term' => env('OMR_API_TERM_PATH', '/api/omr/check-term'),
        ],
    ],
];

