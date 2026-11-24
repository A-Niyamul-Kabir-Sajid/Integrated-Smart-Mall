<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Models\MapGraphEdit;
use App\Services\MapEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Editing the navigation graph itself (ISM-114): the current graph for the 3D
 * editor, changes to it (move or remove a node, connect two nodes, change or
 * remove a walkway) and undoing a change.
 */
class GraphController extends Controller
{
    public function __construct(private MapEditService $edits) {}

    /**
     * The graph as it is now: every node, every walkway once (with its
     * direction), and the changes made so far.
     */
    public function index(MallMap $mallMap): JsonResponse
    {
        $nodes = DB::table('map_nodes')->where('mall_map_id', $mallMap->id)->orderBy('node_code')->get();
        $codes = $nodes->pluck('node_code', 'id');
        $holds = $this->holds($mallMap);

        $pairs = [];
        foreach (DB::table('map_edges')->where('mall_map_id', $mallMap->id)->whereNull('parent_edge_id')->orderBy('id')->get() as $edge) {
            [$a, $b] = [$codes[$edge->from_node_id], $codes[$edge->to_node_id]];
            $key = strcmp($a, $b) <= 0 ? "{$a}|{$b}" : "{$b}|{$a}";
            [$first] = explode('|', $key);
            $pairs[$key] ??= ['from' => $first, 'to' => $first === $a ? $b : $a, 'forward' => false, 'backward' => false, 'step_free' => true, 'closed' => false, 'connector' => $edge->map_connector_id !== null, 'length_m' => round((float) $edge->length_m, 2)];
            $pairs[$key][$a === $first ? 'forward' : 'backward'] = true;
            $pairs[$key]['step_free'] = $pairs[$key]['step_free'] && (bool) $edge->is_accessible;
            $pairs[$key]['closed'] = $pairs[$key]['closed'] || $edge->status === 'closed';
        }

        return response()->json([
            'nodes' => $nodes->map(fn (object $node): array => [
                'code' => $node->node_code,
                'floor' => $node->floor_code,
                'type' => $node->node_type,
                'x' => (float) $node->x_m,
                'y' => (float) $node->y_m,
                'z' => (float) $node->z_m,
                'added' => $node->map_end_point_id !== null || $node->map_path_id !== null,
                'holds' => $holds[$node->id] ?? ($node->map_end_point_id !== null ? 'belongs to a point' : null),
            ])->values(),
            'edges' => collect($pairs)->map(fn (array $pair): array => [
                'from' => $pair['from'],
                'to' => $pair['to'],
                'direction' => $pair['forward'] && $pair['backward'] ? 'both' : ($pair['forward'] ? 'forward' : 'backward'),
                'step_free' => $pair['step_free'],
                'closed' => $pair['closed'],
                'connector' => $pair['connector'],
                'length_m' => $pair['length_m'],
            ])->values(),
            'edits' => $mallMap->graphEdits()->get()->map(fn (MapGraphEdit $edit): array => $this->present($mallMap, $edit))->values(),
        ]);
    }

    public function store(Request $request, MallMap $mallMap): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(MapGraphEdit::ACTIONS)],
            'node_code' => ['nullable', 'required_if:action,move_node,remove_node', 'string', 'max:80'],
            'from_code' => ['nullable', 'required_if:action,connect,edit_edge,remove_edge', 'string', 'max:80'],
            'to_code' => ['nullable', 'required_if:action,connect,edit_edge,remove_edge', 'string', 'max:80'],
            'x' => ['nullable', 'required_if:action,move_node', 'numeric', 'between:-10000,10000'],
            'y' => ['nullable', 'required_if:action,move_node', 'numeric', 'between:-10000,10000'],
            'step_free' => ['nullable', 'boolean'],
            'one_way' => ['nullable', 'boolean'],
            'direction' => ['nullable', Rule::in(['both', 'forward', 'backward'])],
        ]);
        foreach (['step_free', 'one_way'] as $flag) {
            if (array_key_exists($flag, $data) && $data[$flag] !== null) {
                $data[$flag] = $request->boolean($flag);
            } else {
                unset($data[$flag]);
            }
        }
        if (($data['direction'] ?? null) === null) {
            unset($data['direction']);
        }

        try {
            $edit = $this->edits->createGraphEdit($mallMap, $data);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['graph' => $error->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $this->present($mallMap, $edit)], 201)
            : back()->with('status', $edit->describe().' — done.');
    }

    public function destroy(Request $request, MallMap $mallMap, MapGraphEdit $graphEdit): RedirectResponse|JsonResponse
    {
        abort_unless($graphEdit->mall_map_id === $mallMap->id, 404);
        $this->edits->deleteGraphEdit($graphEdit);

        return $request->wantsJson()
            ? response()->json(null, 204)
            : back()->with('status', 'Undone: '.$graphEdit->describe().'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MallMap $map, MapGraphEdit $edit): array
    {
        return [
            'key' => $edit->key,
            'action' => $edit->action,
            'description' => $edit->describe(),
            'problem' => $edit->problem,
            'undo_url' => route('admin.maps.graph.destroy', [$map, $edit]),
        ];
    }

    /**
     * Why each node cannot be removed, by node id.
     *
     * @return array<int, string>
     */
    private function holds(MallMap $map): array
    {
        $holds = [];
        foreach ([
            'map_shop_entrances' => 'is a shop entrance',
            'map_facility_anchors' => 'is a facility',
            'map_checkpoint_anchors' => 'is where visitors stand at a QR code',
            'map_connector_stops' => 'is a stop of a lift, escalator or stairs',
        ] as $table => $why) {
            foreach (DB::table($table)->where('mall_map_id', $map->id)->pluck('map_node_id') as $id) {
                $holds[(int) $id] ??= $why;
            }
        }
        foreach (MapEndPoint::query()->where('mall_map_id', $map->id)->whereNotNull('map_node_id')->pluck('map_node_id') as $id) {
            $holds[(int) $id] ??= 'belongs to a point';
        }

        return $holds;
    }
}
