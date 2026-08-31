<?php

return [
    'city' => 'Surabaya',
    'research_focus' => 'Gunung Anyar',
    'ktp_retention_days' => (int) env('KTP_RETENTION_DAYS', 30),
    'default_pickup_radius_km' => (float) env('SIRKEL_DEFAULT_PICKUP_RADIUS_KM', 10),
    'ai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY'),
        'default_model' => env('OPENAI_MODEL_DEFAULT', 'gpt-5.6-luna'),
        'escalation_model' => env('OPENAI_MODEL_ESCALATION', 'gpt-5.6-terra'),
        'complex_model' => env('OPENAI_MODEL_COMPLEX', 'gpt-5.6-sol'),
        'monthly_budget_usd' => (float) env('OPENAI_MONTHLY_BUDGET_USD', 20),
        'escalation_confidence' => (float) env('OPENAI_ESCALATION_CONFIDENCE', .65),
        'image_detail' => env('OPENAI_IMAGE_DETAIL', 'low'),
        // HTTP reliability: connect timeout protects slow DNS/TCP resolution, while
        // request timeout covers the full OpenAI response. max_attempts includes
        // the first call, so 2 = one retry after a transient connection/429/5xx failure.
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 20),
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
        // PHP max_execution_time must be longer than the external HTTP budget.
        // SIRKEL uses this only while an AI request is running; ordinary requests
        // keep the server's normal execution-time policy.
        'execution_timeout' => (int) env('OPENAI_EXECUTION_TIMEOUT', 150),
        'execution_fallback_buffer' => (int) env('OPENAI_EXECUTION_FALLBACK_BUFFER', 8),
        'max_attempts' => (int) env('OPENAI_MAX_ATTEMPTS', 2),
        'quota' => [
            'asset_intake_free' => 5,
            'condition_description_free' => 20,
            'bulk_ai_free' => 3,
            'asset_intake_price_idr' => 2000,
            'condition_description_price_idr' => 500,
            'bulk_ai_price_idr' => 5000,
        ],
        'retry_delays_ms' => array_values(array_filter(array_map(
            static fn($value) => is_numeric(trim($value)) ? (int) trim($value) : null,
            explode(',', (string) env('OPENAI_RETRY_DELAYS_MS', '750'))
        ), static fn($value) => $value !== null)),
    ],
    'binderbyte' => [
        'base_url' => env('BINDERBYTE_BASE_URL', 'https://api.binderbyte.com'),
        'api_key' => env('BINDERBYTE_API_KEY'),
    ],
];
