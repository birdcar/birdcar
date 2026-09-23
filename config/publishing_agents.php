<?php

return [
    'enabled' => (bool) env('PUBLISHING_AGENTS_ENABLED', false),
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => (int) env('PUBLISHING_AGENTS_HTTP_TIMEOUT', 30),
    ],
    'routes' => [
        'default' => [
            'model' => env('PUBLISHING_AGENTS_DEFAULT_MODEL', 'anthropic/claude-3.5-haiku'),
            'provider' => env('PUBLISHING_AGENTS_DEFAULT_PROVIDER', 'Anthropic'),
            'max_completion_tokens' => env('PUBLISHING_AGENTS_DEFAULT_MAX_COMPLETION_TOKENS'),
            'context_tokens' => env('PUBLISHING_AGENTS_DEFAULT_CONTEXT_TOKENS'),
            'pricing' => [
                'prompt' => env('PUBLISHING_AGENTS_DEFAULT_PROMPT_PRICE', null),
                'completion' => env('PUBLISHING_AGENTS_DEFAULT_COMPLETION_PRICE', null),
                'unit' => env('PUBLISHING_AGENTS_DEFAULT_PRICE_UNIT', 'token'),
            ],
        ],
        'premium' => [
            'model' => env('PUBLISHING_AGENTS_PREMIUM_MODEL'),
            'provider' => env('PUBLISHING_AGENTS_PREMIUM_PROVIDER'),
            'max_completion_tokens' => env('PUBLISHING_AGENTS_PREMIUM_MAX_COMPLETION_TOKENS'),
            'context_tokens' => env('PUBLISHING_AGENTS_PREMIUM_CONTEXT_TOKENS'),
            'pricing' => [
                'prompt' => env('PUBLISHING_AGENTS_PREMIUM_PROMPT_PRICE'),
                'completion' => env('PUBLISHING_AGENTS_PREMIUM_COMPLETION_PRICE'),
                'unit' => env('PUBLISHING_AGENTS_PREMIUM_PRICE_UNIT', 'token'),
            ],
        ],
    ],
    'role_routes' => [
        'review_facts' => env('PUBLISHING_AGENTS_REVIEW_FACTS_ROUTE', 'premium'),
    ],
    'limits' => [
        'max_retries' => 2,
        'fetch_timeout_seconds' => 10,
        'fetch_max_bytes' => 262144,
        'fetch_max_redirects' => 3,
        'max_findings_per_activity' => 25,
        'max_field_bytes' => 32768,
        'exa_web_search_nano_usd' => 7_000_000,
    ],
];
