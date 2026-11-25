<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RouteNotPossible;
use App\Http\Controllers\Controller;
use App\Http\Requests\RouteRequest;
use App\Models\MallMap;
use App\Services\Routing\RoutePlanner;
use Illuminate\Http\JsonResponse;

/**
 * Routes on a published map, by map id or by the mall's published map
 * (ability maps:read).
 */
class RouteController extends Controller
{
    public function __construct(private RoutePlanner $planner) {}

    public function forMap(RouteRequest $request, MallMap $mallMap): JsonResponse
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');

        return $this->answer($request, $mallMap);
    }

    public function forMall(RouteRequest $request, string $mallRef): JsonResponse
    {
        $map = MallMap::publishedFor($mallRef);
        abort_if($map === null, 404, 'This mall has no published map.');

        return $this->answer($request, $map);
    }

    private function answer(RouteRequest $request, MallMap $map): JsonResponse
    {
        try {
            $route = $this->planner->plan($map, (string) $request->validated('from'), $request->stops(), $request->stepFree(), $request->cost());
        } catch (RouteNotPossible $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'reason' => $error->reason,
                'errors' => $error->field === null ? [] : [$error->field => [$error->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $route]);
    }
}
