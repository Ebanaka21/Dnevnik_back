<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);

        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error' => 'Слишком много запросов. Попробуйте позже.',
                'retry_after' => Cache::get($key . ':timer') ?: $decayMinutes * 60
            ], 429, [
                'Retry-After' => Cache::get($key . ':timer') ?: $decayMinutes * 60
            ]);
        }

        Cache::put($key, $attempts + 1, $decayMinutes * 60);

        // Set timer for retry-after header
        if (!Cache::has($key . ':timer')) {
            Cache::put($key . ':timer', now()->addMinutes($decayMinutes)->timestamp, $decayMinutes * 60);
        }

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', Cache::get($key . ':timer') ?: now()->addMinutes($decayMinutes)->timestamp);

        return $response;
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->user() ? $request->user()->id : $request->ip();
        $route = $request->route() ? $request->route()->getName() : $request->path();

        return sha1($userId . '|' . $route);
    }
}
