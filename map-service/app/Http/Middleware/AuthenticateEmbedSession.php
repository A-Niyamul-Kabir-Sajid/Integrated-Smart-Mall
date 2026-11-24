<?php

namespace App\Http\Middleware;

use App\Models\MallMap;
use App\Services\EmbedService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The embedded map's own JSON calls carry the session token from the page
 * in X-Embed-Token. The token must name this map, an active key and a
 * published map. It runs before route model binding, so a bad token learns
 * nothing about which maps exist.
 */
class AuthenticateEmbedSession
{
    public function __construct(private EmbedService $embeds) {}

    public function handle(Request $request, Closure $next): Response
    {
        $map = $request->route('mallMap');
        $mapId = $map instanceof MallMap ? $map->id : (is_string($map) ? $map : '');
        $session = $this->embeds->session($request->header('X-Embed-Token'), $mapId);
        if ($session === null) {
            return response()->json(['message' => 'This map has expired. Reload the page.', 'reason' => 'session'], 401);
        }
        $request->attributes->set('embed', $session);

        return $next($request);
    }
}
