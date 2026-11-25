<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyIssuer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates /api/v1 requests by API key, sent as
 * "Authorization: Bearer ism_..." or "X-Api-Key: ism_...", and checks the
 * ability named in the route (api.key:maps:read).
 */
class AuthenticateApiKey
{
    public function __construct(private ApiKeyIssuer $issuer) {}

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $plain = $request->bearerToken() ?? $request->header('X-Api-Key');
        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'Send an API key: Authorization: Bearer <key> or X-Api-Key: <key>.'], 401);
        }

        $key = $this->issuer->find($plain);
        if ($key === null) {
            return response()->json(['message' => 'The API key is unknown, revoked or expired.'], 401);
        }
        if (! $key->can($ability)) {
            return response()->json(['message' => "This API key may not do this (needs {$ability})."], 403);
        }

        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
