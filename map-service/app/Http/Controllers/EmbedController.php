<?php

namespace App\Http\Controllers;

use App\Exceptions\RouteNotPossible;
use App\Http\Requests\RouteRequest;
use App\Models\Annotation;
use App\Models\ApiKey;
use App\Models\MallMap;
use App\Services\EmbedService;
use App\Services\PackagePresenter;
use App\Services\Routing\RoutePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The embedded map page (opened from a signed link) and the two JSON calls
 * it makes: the map data and routes.
 */
class EmbedController extends Controller
{
    public function __construct(
        private EmbedService $embeds,
        private PackagePresenter $presenter,
        private RoutePlanner $planner,
    ) {}

    public function show(Request $request, MallMap $mallMap): Response
    {
        $key = ApiKey::query()->find($request->query('key'));
        if ($key === null || ! $key->isActive() || ! $key->can('maps:read') || $key->origins() === []) {
            return response()->view('embed.unavailable', ['message' => 'This map link is no longer valid.'], 403);
        }
        if (! $mallMap->isPublished()) {
            return response()->view('embed.unavailable', ['message' => 'This map is not available right now.'], 404)
                ->header('Content-Security-Policy', $this->embeds->contentSecurityPolicy($key));
        }

        $lang = in_array($request->query('lang'), EmbedService::LANGUAGES, true) ? (string) $request->query('lang') : 'en';
        $config = [
            'mapId' => $mallMap->id,
            'token' => $this->embeds->token($key, $mallMap),
            'dataUrl' => route('embed.data', $mallMap),
            'routeUrl' => route('embed.route', $mallMap),
            'from' => is_string($request->query('from')) ? $request->query('from') : null,
            'to' => array_values(array_filter((array) $request->query('to', []), 'is_string')),
            'stepFree' => $request->boolean('step_free'),
            'cost' => $request->query('cost') === 'distance' ? 'distance' : 'time',
            'lang' => $lang,
            'origins' => $key->origins(),
        ];

        return response()
            ->view('embed.show', ['config' => $config, 'lang' => $lang, 'map' => $mallMap])
            ->header('Content-Security-Policy', $this->embeds->contentSecurityPolicy($key))
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    public function data(MallMap $mallMap): JsonResponse
    {
        $package = $this->presenter->package($mallMap);
        $nodeCodes = DB::table('map_nodes')->where('mall_map_id', $mallMap->id)->pluck('node_code', 'id');
        $nodeFloors = DB::table('map_nodes')->where('mall_map_id', $mallMap->id)->pluck('floor_code', 'id');

        $edges = [];
        foreach (DB::table('map_edges')->where('mall_map_id', $mallMap->id)->whereNull('map_connector_id')->orderBy('id')->get() as $edge) {
            $pair = [$nodeCodes[$edge->from_node_id], $nodeCodes[$edge->to_node_id]];
            sort($pair);
            $id = implode('|', $pair);
            if (! isset($edges[$id]) || $edge->status === 'closed') {
                $edges[$id] = [$pair[0], $pair[1], $nodeFloors[$edge->from_node_id], $edge->status];
            }
        }

        $signs = $mallMap->annotations()->where('kind', 'shop_sign')->whereNotNull('unit_code')->get()
            ->mapWithKeys(fn (Annotation $sign): array => [$sign->unit_code => $sign->text]);
        $labels = [];
        foreach (DB::table('map_shop_entrances')->where('mall_map_id', $mallMap->id)->get() as $entrance) {
            $labels[$nodeCodes[$entrance->map_node_id]] = $signs[$entrance->unit_code] ?? $entrance->unit_code;
        }
        foreach (DB::table('map_facility_anchors')->where('mall_map_id', $mallMap->id)->get() as $facility) {
            $labels[$nodeCodes[$facility->map_node_id]] = $facility->facility_name;
        }
        foreach (DB::table('map_end_points')->where('mall_map_id', $mallMap->id)->whereNotNull('map_node_id')->get() as $point) {
            $labels[$nodeCodes[$point->map_node_id]] = $point->name;
        }

        return response()->json(['data' => [
            'map' => ['id' => $mallMap->id, 'name' => $mallMap->name, 'mall_ref' => $mallMap->mall_ref],
            'floors' => array_map(fn (array $floor): array => [
                'floor_code' => $floor['floor_code'],
                'name' => $floor['name'] ?? $floor['floor_code'],
                'elevation_m' => $floor['elevation_m'] ?? 0,
                'plan' => isset($floor['plan']['url']) ? array_intersect_key($floor['plan'], array_flip(['url', 'width_px', 'height_px', 'metres_per_pixel', 'world_origin_x_m', 'world_origin_y_m'])) : null,
            ], $package['floors']),
            'nodes' => DB::table('map_nodes')->where('mall_map_id', $mallMap->id)->orderBy('node_code')->get()
                ->map(fn (object $node): array => ['code' => $node->node_code, 'floor' => $node->floor_code, 'type' => $node->node_type, 'x' => (float) $node->x_m, 'y' => (float) $node->y_m])->values(),
            'edges' => array_values($edges),
            'places' => $this->presenter->places($mallMap),
            'signs' => (object) $signs->all(),
            'labels' => (object) $labels,
        ]]);
    }

    public function route(RouteRequest $request, MallMap $mallMap): JsonResponse
    {
        try {
            $route = $this->planner->plan($mallMap, (string) $request->validated('from'), $request->stops(), $request->stepFree(), $request->cost());
        } catch (RouteNotPossible $error) {
            return response()->json(['message' => $error->getMessage(), 'reason' => $error->reason, 'errors' => $error->field === null ? [] : [$error->field => [$error->getMessage()]]], 422);
        }

        return response()->json(['data' => $route]);
    }
}
