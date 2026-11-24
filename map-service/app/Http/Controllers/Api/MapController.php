<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Services\PackagePresenter;
use Illuminate\Http\JsonResponse;

/**
 * Published maps for API clients (ability maps:read).
 */
class MapController extends Controller
{
    public function __construct(private PackagePresenter $presenter) {}

    public function index(): JsonResponse
    {
        $maps = MallMap::query()->where('status', 'published')->withCount('annotations')->latest('updated_at')->get();

        return response()->json(['data' => $maps->map(fn (MallMap $map): array => $this->presenter->summary($map))->values()]);
    }

    public function show(MallMap $mallMap): JsonResponse
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');

        return response()->json($this->presenter->package($mallMap));
    }
}
