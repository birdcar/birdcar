<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Uri;
use Laravel\Fortify\Contracts\PasswordResetResponse;

class AdminPasswordResetResponse implements PasswordResetResponse
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($this->status)], 200);
        }

        return redirect()->to(Uri::of(config()->string('admin.url'))->withPath('login')->value())
            ->with('status', trans($this->status));
    }
}
