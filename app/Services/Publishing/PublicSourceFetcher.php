<?php

namespace App\Services\Publishing;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class PublicSourceFetcher
{
    /** @var callable(string): list<string>|null */
    private $resolver = null;

    /** @var callable(string, array<string, mixed>): array{status: int, headers: array<string, string>, body: string}|null */
    private $transport = null;

    public function __construct(private readonly PublishingFingerprint $fingerprint) {}

    /** @param callable(string): list<string> $resolver */
    public function useResolver(callable $resolver): void
    {
        $this->resolver = $resolver;
    }

    /** @param callable(string, array<string, mixed>): array{status: int, headers: array<string, string>, body: string} $transport */
    public function useTransport(callable $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * @return array{url: string, final_url: string, extracted_text: ?string, content_hash: ?string, unresolved_reason: ?string, hops: int}
     */
    public function fetch(string $url): array
    {
        $current = $url;
        $maxRedirects = (int) config('publishing_agents.limits.fetch_max_redirects', 3);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $ip = $this->validatedIpForUrl($current);
            $response = $this->send($current, $ip);
            $status = $response['status'];
            $headers = $this->lowerHeaders($response['headers']);

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = $headers['location'] ?? null;
                if (! is_string($location) || $location === '') {
                    return $this->unresolved($url, $current, 'Redirect response omitted a location.', $hop);
                }
                if ($hop === $maxRedirects) {
                    return $this->unresolved($url, $current, 'Too many redirects.', $hop);
                }
                $current = $this->resolveRedirect($current, $location);

                continue;
            }

            if ($status < 200 || $status >= 300) {
                return $this->unresolved($url, $current, 'Source returned HTTP '.$status.'.', $hop);
            }

            $type = $headers['content-type'] ?? 'text/plain';
            if (! str_contains($type, 'text/') && ! str_contains($type, 'application/json') && ! str_contains($type, 'application/xhtml+xml')) {
                return $this->unresolved($url, $current, 'Source content type is not textual.', $hop);
            }

            $body = $response['body'];
            if (strlen($body) > (int) config('publishing_agents.limits.fetch_max_bytes', 262144)) {
                return $this->unresolved($url, $current, 'Source exceeded the retrieval size limit.', $hop);
            }

            $text = $this->extractText($body);

            return [
                'url' => $url,
                'final_url' => $current,
                'extracted_text' => $text,
                'content_hash' => $this->fingerprint->hash($text),
                'unresolved_reason' => null,
                'hops' => $hop,
            ];
        }

        return $this->unresolved($url, $current, 'Too many redirects.', $maxRedirects);
    }

    private function validatedIpForUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Only HTTP(S) public source URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || preg_match('/[[:cntrl:]]/', $url) === 1) {
            throw new InvalidArgumentException('Source URLs cannot include credentials or control characters.');
        }

        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Source URL port is not allowed.');
        }

        $ips = ($this->resolver) !== null ? ($this->resolver)($host) : $this->resolveHost($host);
        if ($ips === []) {
            throw new InvalidArgumentException('Source host did not resolve.');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new InvalidArgumentException('Source host resolves to a non-public address.');
            }
        }

        return $ips[0];
    }

    /** @return list<string> */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        if ($records === false) {
            return [];
        }

        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function send(string $url, string $ip): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, ['ip' => $ip]);
        }

        $response = Http::withoutRedirecting()
            ->timeout((int) config('publishing_agents.limits.fetch_timeout_seconds', 10))
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [$this->curlResolveEntry($url, $ip)]]])
            ->get($url);

        /** @var array<string, string> $headers */
        $headers = collect($response->headers())->map(fn (array $values): string => implode(', ', $values))->all();

        return [
            'status' => $response->status(),
            'headers' => $headers,
            'body' => $response->body(),
        ];
    }

    private function curlResolveEntry(string $url, string $ip): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return $host.':'.$port.':'.$ip;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function lowerHeaders(array $headers): array
    {
        $lower = [];
        foreach ($headers as $key => $value) {
            $lower[strtolower($key)] = $value;
        }

        return $lower;
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '', '/');

        return $scheme.'://'.$host.$port.$dir.'/'.$location;
    }

    private function extractText(string $body): string
    {
        $withoutScripts = preg_replace('/<(script|style)\b[^>]*>.*?<\/\\1>/is', ' ', $body) ?? $body;
        $text = html_entity_decode(strip_tags($withoutScripts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(mb_substr($text, 0, 32768));
    }

    /** @return array{url: string, final_url: string, extracted_text: null, content_hash: null, unresolved_reason: string, hops: int} */
    private function unresolved(string $url, string $finalUrl, string $reason, int $hops): array
    {
        return [
            'url' => $url,
            'final_url' => $finalUrl,
            'extracted_text' => null,
            'content_hash' => null,
            'unresolved_reason' => $reason,
            'hops' => $hops,
        ];
    }
}
