<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\Factory;
use RuntimeException;

class OpenRouterBilling
{
    public function __construct(private readonly Factory $http) {}

    /** @return array<string, mixed> */
    public function generation(string $id): array
    {
        $key = (string) config('ai.providers.openrouter.key', '');
        if ($key === '') {
            throw new RuntimeException('OpenRouter credentials are not configured.');
        }

        $response = $this->http
            ->baseUrl(rtrim((string) config('ai.providers.openrouter.url', 'https://openrouter.ai/api/v1'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->timeout((int) config('publishing_agents.http_timeout', 30))
            ->get('/generation', ['id' => $id])
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('OpenRouter returned malformed generation metadata.');
        }

        return $response;
    }
}
