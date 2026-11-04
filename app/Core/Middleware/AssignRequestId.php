<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an identifier that appears in all log lines written while
 * handling it, in audit records and in the X-Request-Id response header.
 * A well-formed incoming X-Request-Id (e.g. from a proxy) is reused.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER);
        $requestId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Log::shareContext([self::ATTRIBUTE => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
