<?php

return [
    /*
     * Curated OpenRouter models that role agents may use or be overridden to.
     * Pricing is intentionally absent: OpenRouter key limits own spend enforcement.
     */
    'models' => [
        'google/gemini-3.8-flash' => ['label' => 'Gemini 3.8 Flash', 'reasoning' => true],
        'deepseek/deepseek-v4-pro-0813' => ['label' => 'DeepSeek V4 Pro', 'reasoning' => true],
        'deepseek/deepseek-v4.1-flash' => ['label' => 'DeepSeek V4.1 Flash', 'reasoning' => true],
        'openrouter/auto' => ['label' => 'OpenRouter Auto Router', 'reasoning' => false],
    ],
    'http_timeout' => 50,
    'limits' => [
        'max_retries' => 2,
        'fetch_timeout_seconds' => 10,
        'fetch_max_bytes' => 262144,
        'fetch_max_redirects' => 3,
        'max_findings_per_activity' => 25,
        'max_field_bytes' => 32768,
    ],
];
