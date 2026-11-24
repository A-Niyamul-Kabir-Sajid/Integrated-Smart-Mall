<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Services\EndPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Administrators add end points (destinations) to a map on this page or by
 * clicking the floor in the 3D editor, and see each one's key (ISM-114).
 * The 3D editor talks JSON to the same routes.
 */
class EndPointController extends Controller
{
    public function __construct(private EndPointService $endPoints) {}

    public function index(Request $request, MallMap $mallMap): View|JsonResponse
    {
        $points = $mallMap->endPoints()->get();
        if ($request->wantsJson()) {
            return response()->json(['data' => $points->map(fn (MapEndPoint $point): array => $this->present($mallMap, $point))->values()]);
        }

        return view('admin.maps.end-points', [
            'map' => $mallMap,
            'points' => $points,
            'floors' => $mallMap->floors()->get(),
            'appUrl' => fn (MapEndPoint $point): ?string => $this->appUrl($mallMap, $point),
        ]);
    }

    public function store(Request $request, MallMap $mallMap): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'floor_code' => ['required', 'string', 'max:20'],
            'x' => ['required', 'numeric', 'between:-10000,10000'],
            'y' => ['required', 'numeric', 'between:-10000,10000'],
        ]);

        try {
            $point = $this->endPoints->create($mallMap, $data['name'], $data['floor_code'], (float) $data['x'], (float) $data['y']);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['x' => $error->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->present($mallMap, $point)], 201);
        }

        return back()->with('status', "End point “{$point->name}” added. Its key is {$point->key}.");
    }

    public function update(Request $request, MallMap $mallMap, MapEndPoint $endPoint): RedirectResponse|JsonResponse
    {
        abort_unless($endPoint->mall_map_id === $mallMap->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->endPoints->rename($endPoint, $data['name']);

        return $request->wantsJson()
            ? response()->json(['data' => $this->present($mallMap, $endPoint)])
            : back()->with('status', "End point {$endPoint->key} renamed to “{$endPoint->name}”.");
    }

    public function destroy(Request $request, MallMap $mallMap, MapEndPoint $endPoint): RedirectResponse|JsonResponse
    {
        abort_unless($endPoint->mall_map_id === $mallMap->id, 404);
        $this->endPoints->delete($endPoint);

        return $request->wantsJson()
            ? response()->json(null, 204)
            : back()->with('status', "End point “{$endPoint->name}” ({$endPoint->key}) deleted; links that use its key no longer find it.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MallMap $map, MapEndPoint $point): array
    {
        return [
            'key' => $point->key,
            'name' => $point->name,
            'floor_code' => $point->floor_code,
            'position' => ['x' => $point->x_m, 'y' => $point->y_m, 'z' => $point->z_m],
            'linked' => $point->isLinked(),
            'link_m' => $point->link_m,
            'joined_at' => $this->junction($point),
            'place' => $point->place(),
            'app_url' => $this->appUrl($map, $point),
            'update_url' => route('admin.maps.end-points.update', [$map, $point]),
        ];
    }

    /**
     * Where the end point meets the walkway, for drawing its link in 3D.
     *
     * @return array{x: float, y: float, z: float}|null
     */
    private function junction(MapEndPoint $point): ?array
    {
        if ($point->map_node_id === null) {
            return null;
        }
        $node = DB::table('map_edges')->join('map_nodes', 'map_nodes.id', '=', 'map_edges.to_node_id')
            ->where('map_edges.from_node_id', $point->map_node_id)->first(['map_nodes.x_m', 'map_nodes.y_m', 'map_nodes.z_m']);

        return $node === null ? null : ['x' => (float) $node->x_m, 'y' => (float) $node->y_m, 'z' => (float) $node->z_m];
    }

    private function appUrl(MallMap $map, MapEndPoint $point): ?string
    {
        if ($map->mall_ref === null) {
            return null;
        }

        return strtr((string) config('mapservice.app_map_url'), ['{mall}' => rawurlencode($map->mall_ref)]).'?to='.rawurlencode($point->place());
    }
}
