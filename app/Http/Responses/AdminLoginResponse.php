<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class AdminLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isAdminUrl($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to(Uri::of(config()->string('admin.url'))->withPath('/')->value());
    }

    private function isAdminUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $origin = $this->origin($url);

        return $origin !== null && $origin === $this->origin(config()->string('admin.url'));
    }

    /**
     * Normalized scheme://host[:port], or null for anything that is not an absolute http(s) URL.
     */
    private function origin(string $url): ?string
    {
        try {
            $uri = Uri::of($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (! in_array($uri->scheme(), ['http', 'https'], true) || blank($uri->host())) {
            return null;
        }

        return $uri->scheme().'://'.$uri->host().($uri->port() === null ? '' : ':'.$uri->port());
    }
}
