<?php

namespace App\Http\Middleware;

use App\Services\MarketingSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPrivateIndexing
{
    public function __construct(private MarketingSite $marketing) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->marketing->indexable() || $request->getHost() !== $this->marketing->host() || ! $request->routeIs('public.*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
