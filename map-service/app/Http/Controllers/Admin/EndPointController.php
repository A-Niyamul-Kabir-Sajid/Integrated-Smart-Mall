<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Models\MapPath;
use App\Services\CheckpointRegistry;
use App\Services\MapEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Administrators' points on a map (ISM-114): a node, a shop entrance, a
 * facility or a QR code, each with a unique key, added on the "Points and
 * paths" page or in the 3D editor. The 3D editor reads the same routes as
 * JSON, including the paths and the walkways the edits add, to draw them.
 */
class EndPointController extends Controller
{
    public function __construct(private MapEditService $edits, private CheckpointRegistry $checkpoints) {}

    public function index(Request $request, MallMap $mallMap): View|JsonResponse
    {
        $points = $mallMap->endPoints()->get();
        $paths = $mallMap->paths()->get();
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $points->map(fn (MapEndPoint $point): array => $this->present($mallMap, $point))->values(),
                'paths' => $paths->map(fn (MapPath $path): array => PathController::present($mallMap, $path))->values(),
                'overlay' => $this->overlay($mallMap),
            ]);
        }

        return view('admin.maps.end-points', [
            'map' => $mallMap,
            'points' => $points->map(fn (MapEndPoint $point): array => $this->present($mallMap, $point)),
            'paths' => $paths->map(fn (MapPath $path): array => PathController::present($mallMap, $path)),
            'floors' => $mallMap->floors()->get(),
            'kinds' => MapEndPoint::KINDS,
        ]);
    }

    public function store(Request $request, MallMap $mallMap): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(MapEndPoint::KINDS))],
            'name' => ['required', 'string', 'max:120'],
            'floor_code' => ['required', 'string', 'max:20'],
            'x' => ['required', 'numeric', 'between:-10000,10000'],
            'y' => ['required', 'numeric', 'between:-10000,10000'],
            'unit_code' => ['nullable', 'required_if:kind,shop_entrance', 'string', 'max:40'],
            'is_primary' => ['nullable', 'boolean'],
            'facility_type' => ['nullable', 'required_if:kind,facility', 'string', 'max:40'],
            'checkpoint_code' => ['nullable', 'required_if:kind,checkpoint', 'string', 'max:60'],
            'qr_yaw' => ['nullable', 'required_if:kind,checkpoint', 'numeric', 'between:-360,720'],
            'qr_z' => ['nullable', 'numeric', 'between:-1000,1000'],
            'qr_height' => ['nullable', 'numeric', 'between:0.2,3'],
        ], [
            'unit_code.required_if' => 'Give the shop unit code of the entrance, for example G-05.',
            'facility_type.required_if' => 'Choose the facility type.',
            'checkpoint_code.required_if' => 'Give the QR code a code, for example G-C10.',
            'qr_yaw.required_if' => 'Give the direction the QR code faces, in degrees.',
        ]);

        try {
            $point = $this->edits->createPoint($mallMap, [
                'kind' => $data['kind'],
                'name' => $data['name'],
                'floor_code' => $data['floor_code'],
                'x' => (float) $data['x'],
                'y' => (float) $data['y'],
                'unit_code' => $data['unit_code'] ?? null,
                'is_primary' => $request->boolean('is_primary'),
                'facility_type' => $data['facility_type'] ?? null,
                'checkpoint_code' => $data['checkpoint_code'] ?? null,
                'qr_yaw' => isset($data['qr_yaw']) ? (float) $data['qr_yaw'] : null,
                'qr_z' => isset($data['qr_z']) ? (float) $data['qr_z'] : null,
                'qr_height' => isset($data['qr_height']) ? (float) $data['qr_height'] : null,
            ]);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['point' => $error->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->present($mallMap, $point)], 201);
        }

        $note = $point->link_m === null ? ' It is not joined to a walkway yet: draw a path to it.' : '';

        return back()->with('status', MapEndPoint::KINDS[$point->kind]." “{$point->name}” added. Its key is {$point->key}.".$note);
    }

    public function update(Request $request, MallMap $mallMap, MapEndPoint $endPoint): RedirectResponse|JsonResponse
    {
        abort_unless($endPoint->mall_map_id === $mallMap->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        try {
            $this->edits->renamePoint($endPoint, $data['name']);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['name' => $error->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $this->present($mallMap, $endPoint->refresh())])
            : back()->with('status', "Point {$endPoint->key} renamed to “{$endPoint->name}”.");
    }

    public function destroy(Request $request, MallMap $mallMap, MapEndPoint $endPoint): RedirectResponse|JsonResponse
    {
        abort_unless($endPoint->mall_map_id === $mallMap->id, 404);
        $this->edits->deletePoint($endPoint);

        return $request->wantsJson()
            ? response()->json(null, 204)
            : back()->with('status', "“{$endPoint->name}” ({$endPoint->key}) deleted; links that use its key no longer find it.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MallMap $map, MapEndPoint $point): array
    {
        $qrUrl = null;
        if ($point->kind === 'checkpoint' && $map->mall_ref !== null) {
            $checkpoint = Checkpoint::query()->where('mall_ref', $map->mall_ref)->where('checkpoint_code', $point->checkpoint_code)->first();
            $qrUrl = $checkpoint === null ? null : $this->checkpoints->qrUrl($checkpoint);
        }
        $connected = $point->map_node_id !== null && DB::table('map_edges')
            ->where(fn ($q) => $q->where('from_node_id', $point->map_node_id)->orWhere('to_node_id', $point->map_node_id))->exists();

        return [
            'key' => $point->key,
            'kind' => $point->kind,
            'kind_label' => MapEndPoint::KINDS[$point->kind] ?? $point->kind,
            'name' => $point->name,
            'floor_code' => $point->floor_code,
            'position' => ['x' => $point->x_m, 'y' => $point->y_m, 'z' => $point->z_m],
            'unit_code' => $point->unit_code,
            'is_primary' => $point->is_primary,
            'facility_type' => $point->facility_type,
            'checkpoint_code' => $point->checkpoint_code,
            'qr' => $point->qr,
            'qr_url' => $qrUrl,
            'linked' => $point->isLinked(),
            'connected' => $connected,
            'link_m' => $point->link_m,
            'problem' => $point->problem,
            'place' => $point->place(),
            'app_url' => $this->appUrl($map, $point),
            'joined_at' => $this->junction($point),
            'update_url' => route('admin.maps.end-points.update', [$map, $point]),
        ];
    }

    /**
     * The walkways the edits added, for the 3D editor: one entry per node pair.
     *
     * @return array{edges: list<array<string, mixed>>}
     */
    private function overlay(MallMap $map): array
    {
        $edges = [];
        $rows = DB::table('map_edges as e')
            ->join('map_nodes as a', 'a.id', '=', 'e.from_node_id')
            ->join('map_nodes as b', 'b.id', '=', 'e.to_node_id')
            ->where('e.mall_map_id', $map->id)
            ->where(fn ($q) => $q->whereNotNull('e.map_end_point_id')->orWhereNotNull('e.map_path_id'))
            ->whereNull('e.parent_edge_id')
            ->get(['e.from_node_id', 'e.to_node_id', 'e.map_path_id', 'e.status', 'a.floor_code', 'a.x_m as ax', 'a.y_m as ay', 'a.z_m as az', 'b.x_m as bx', 'b.y_m as by', 'b.z_m as bz']);
        foreach ($rows as $row) {
            $pair = min($row->from_node_id, $row->to_node_id).'-'.max($row->from_node_id, $row->to_node_id);
            $edges[$pair] ??= [
                'floor_code' => $row->floor_code,
                'from' => ['x' => (float) $row->ax, 'y' => (float) $row->ay, 'z' => (float) $row->az],
                'to' => ['x' => (float) $row->bx, 'y' => (float) $row->by, 'z' => (float) $row->bz],
                'kind' => $row->map_path_id === null ? 'link' : 'path',
                'closed' => $row->status === 'closed',
            ];
        }

        return ['edges' => array_values($edges)];
    }

    /**
     * Where the point meets the walkway, for drawing its link in 3D.
     *
     * @return array{x: float, y: float, z: float}|null
     */
    private function junction(MapEndPoint $point): ?array
    {
        if ($point->map_node_id === null || $point->link_m === null) {
            return null;
        }
        $node = DB::table('map_edges')->join('map_nodes', 'map_nodes.id', '=', 'map_edges.to_node_id')
            ->where('map_edges.from_node_id', $point->map_node_id)->where('map_edges.map_end_point_id', $point->id)
            ->first(['map_nodes.x_m', 'map_nodes.y_m', 'map_nodes.z_m']);

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
