<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Uri;
use Laravel\Fortify\Contracts\LogoutResponse;

class AdminLogoutResponse implements LogoutResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $request->session()->forget('url.intended');

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->to(Uri::of(config()->string('admin.url'))->withPath('login')->value());
    }
}
