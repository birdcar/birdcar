<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class AdminLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && $this->safeAdminUrl($intended, $request)) {
            $request->session()->forget('url.intended');

            return redirect()->to($intended);
        }

        $request->session()->forget('url.intended');

        return redirect()->to($this->adminUrl('/'));
    }

    private function safeAdminUrl(string $url, Request $request): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return $this->origin($url) !== null && $this->origin($url) === $this->origin((string) config('admin.url'));
    }

    private function adminUrl(string $path): string
    {
        return rtrim((string) config('admin.url'), '/').'/'.ltrim($path, '/');
    }

    private function origin(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $portSuffix = is_int($port) && $port !== $defaultPort ? ':'.$port : '';

        return strtolower($scheme).'://'.strtolower($host).$portSuffix;
    }
}
