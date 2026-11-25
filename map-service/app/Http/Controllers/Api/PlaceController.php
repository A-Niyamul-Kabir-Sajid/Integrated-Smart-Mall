<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Services\PackagePresenter;
use Illuminate\Http\JsonResponse;

/**
 * The mall's published map and the places a route can start or end at
 * (ability maps:read).
 */
class PlaceController extends Controller
{
    public function __construct(private PackagePresenter $presenter) {}

    public function mall(string $mallRef): JsonResponse
    {
        $map = MallMap::publishedFor($mallRef);
        abort_if($map === null, 404, 'This mall has no published map.');

        return response()->json(['data' => $this->presenter->summary($map) + ['places' => $this->presenter->places($map)]]);
    }

    public function places(MallMap $mallMap): JsonResponse
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');

        return response()->json(['data' => $this->presenter->places($mallMap)]);
    }
}
