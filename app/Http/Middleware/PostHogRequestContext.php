<?php

namespace App\Http\Middleware;

use App\Services\PostHogService;
use Closure;
use Illuminate\Http\Request;
use PostHog\PostHog;
use Symfony\Component\HttpFoundation\Response;

class PostHogRequestContext
{
    public function __construct(private PostHogService $posthog) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = PostHog::contextFromHeaders($request->headers->all());
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null && $userId !== '') {
            $context['distinctId'] = (string) $userId;
        }

        return $this->posthog->withContext(
            $context,
            fn (): Response => $next($request),
        );
    }
}
