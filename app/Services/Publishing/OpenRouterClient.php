<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class OpenRouterClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function chat(array $request): array
    {
        $apiKey = (string) config('publishing_agents.openrouter.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenRouter credentials are not configured.');
        }

        $response = $this->request()
            ->withToken($apiKey)
            ->post('/chat/completions', $request)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('OpenRouter returned a malformed response.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function generation(string $id): array
    {
        $apiKey = (string) config('publishing_agents.openrouter.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenRouter credentials are not configured.');
        }

        $response = $this->request()
            ->withToken($apiKey)
            ->get('/generation', ['id' => $id])
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('OpenRouter returned malformed generation metadata.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    public function pricedEndpoint(array $route): array
    {
        if (! (bool) config('publishing_agents.enabled', false)) {
            throw new RuntimeException('Publishing agents are disabled.');
        }

        $apiKey = (string) config('publishing_agents.openrouter.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenRouter credentials are not configured.');
        }

        $model = $route['model'] ?? null;
        $provider = $route['provider'] ?? null;
        $pricing = is_array($route['pricing'] ?? null) ? $route['pricing'] : [];
        $prompt = $pricing['prompt'] ?? null;
        $completion = $pricing['completion'] ?? null;
        $maxCompletionTokens = $route['max_completion_tokens'] ?? null;
        $contextTokens = $route['context_tokens'] ?? null;

        if (! is_string($model) || $model === '' || ! is_string($provider) || $provider === '') {
            throw new RuntimeException('Publishing agent model and provider must be explicit.');
        }

        if (! is_string($prompt) || $prompt === '' || ! is_string($completion) || $completion === '') {
            throw new RuntimeException('Publishing agent pricing is missing.');
        }

        if (! is_numeric($maxCompletionTokens) || (int) $maxCompletionTokens <= 0 || ! is_numeric($contextTokens) || (int) $contextTokens <= 0) {
            throw new RuntimeException('Publishing agent context and output limits must be configured.');
        }

        $unit = is_string($pricing['unit'] ?? null) ? (string) $pricing['unit'] : 'token';

        return [
            'model' => $model,
            'provider' => $provider,
            'pricing' => [
                'prompt' => $prompt,
                'completion' => $completion,
                'unit' => $unit,
            ],
            'max_price' => $this->routingMaxPrice($prompt, $completion, $unit),
            'max_completion_tokens' => (int) $maxCompletionTokens,
            'context_tokens' => (int) $contextTokens,
        ];
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @param  array<string, mixed>  $request
     * @return array{reserved_nano_usd: int, prompt_tokens: int, completion_tokens: int, plugin_nano_usd: int, price_snapshot: array<string, mixed>, request_bound: array<string, mixed>}
     */
    public function quote(array $endpoint, array $request): array
    {
        $pricing = is_array($endpoint['pricing'] ?? null) ? $endpoint['pricing'] : [];
        $unit = (string) ($pricing['unit'] ?? 'token');
        $promptTokens = (int) ($request['prompt_tokens'] ?? $endpoint['context_tokens'] ?? 0);
        $completionTokens = (int) ($request['max_completion_tokens'] ?? $endpoint['max_completion_tokens'] ?? 0);
        $pluginNanoUsd = (int) ($request['plugin_nano_usd'] ?? 0);

        if ($promptTokens <= 0 || $completionTokens <= 0) {
            throw new RuntimeException('A conservative token bound is required before spending.');
        }

        $promptPrice = (string) ($pricing['prompt'] ?? '');
        $completionPrice = (string) ($pricing['completion'] ?? '');
        if ($promptPrice === '' || $completionPrice === '') {
            throw new RuntimeException('Endpoint pricing is missing.');
        }

        $promptNano = $this->priceForTokens($promptPrice, $promptTokens, $unit);
        $completionNano = $this->priceForTokens($completionPrice, $completionTokens, $unit);
        $reserved = $promptNano + $completionNano + max(0, $pluginNanoUsd);

        return [
            'reserved_nano_usd' => $reserved,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'plugin_nano_usd' => max(0, $pluginNanoUsd),
            'price_snapshot' => $endpoint,
            'request_bound' => [
                'prompt_tokens' => $promptTokens,
                'max_completion_tokens' => $completionTokens,
                'plugin_nano_usd' => max(0, $pluginNanoUsd),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function chatRequest(array $endpoint, array $messages, array $schema = [], array $extra = []): array
    {
        $request = array_merge($extra, [
            'model' => $endpoint['model'],
            'messages' => $messages,
            'max_completion_tokens' => (int) $endpoint['max_completion_tokens'],
            'provider' => [
                'only' => [(string) $endpoint['provider']],
                'allow_fallbacks' => false,
                'require_parameters' => true,
                'max_price' => $endpoint['max_price'] ?? $this->routingMaxPrice(
                    (string) data_get($endpoint, 'pricing.prompt', ''),
                    (string) data_get($endpoint, 'pricing.completion', ''),
                    (string) data_get($endpoint, 'pricing.unit', 'token'),
                ),
            ],
        ]);

        if ($schema !== []) {
            $request['response_format'] = $schema;
        }

        return $request;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim((string) config('publishing_agents.openrouter.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('publishing_agents.openrouter.timeout', 30));
    }

    /** @return array{prompt: string, completion: string} */
    private function routingMaxPrice(string $prompt, string $completion, string $unit): array
    {
        return [
            'prompt' => $this->routingPricePerMillion($prompt, $unit),
            'completion' => $this->routingPricePerMillion($completion, $unit),
        ];
    }

    private function routingPricePerMillion(string $decimalUsd, string $unit): string
    {
        if (! in_array($unit, ['million_tokens', 'per_million', 'per_million_tokens'], true)) {
            return $this->multiplyDecimalByInteger($decimalUsd, 1_000_000);
        }

        $this->decimalUsdToNanoUsd($decimalUsd);

        return $decimalUsd;
    }

    private function multiplyDecimalByInteger(string $decimalUsd, int $multiplier): string
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $decimalUsd)) {
            throw new RuntimeException('Endpoint pricing must be a non-negative decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $decimalUsd, 2), 2, '');
        $scale = strlen($fraction);
        $product = (int) ($whole.$fraction) * $multiplier;
        $digits = str_pad((string) $product, $scale + 1, '0', STR_PAD_LEFT);
        if ($scale === 0) {
            return $digits;
        }

        $wholePart = substr($digits, 0, -$scale) ?: '0';
        $fractionPart = rtrim(substr($digits, -$scale), '0');

        return $fractionPart === '' ? $wholePart : $wholePart.'.'.$fractionPart;
    }

    private function priceForTokens(string $decimalUsd, int $tokens, string $unit): int
    {
        $scale = in_array($unit, ['million_tokens', 'per_million', 'per_million_tokens'], true) ? 1_000_000 : 1;
        $numerator = $this->decimalUsdToNanoUsd($decimalUsd) * $tokens;

        return intdiv($numerator + $scale - 1, $scale);
    }

    private function decimalUsdToNanoUsd(string $decimalUsd): int
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $decimalUsd)) {
            throw new RuntimeException('Endpoint pricing must be a non-negative decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $decimalUsd, 2), 2, '');
        $fraction = substr(str_pad($fraction, 9, '0'), 0, 9);
        $extra = substr($decimalUsd, strpos($decimalUsd, '.') === false ? strlen($decimalUsd) : strpos($decimalUsd, '.') + 10);
        $nano = ((int) $whole * 1_000_000_000) + (int) $fraction;

        if ($extra !== '' && preg_match('/[1-9]/', $extra) === 1) {
            $nano++;
        }

        return $nano;
    }
}
