<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Models\MapPath;
use App\Services\MapEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Walkways administrators draw on a map (ISM-114): in the 3D editor by
 * clicking corners on the floor, or on the "Points and paths" page as a list
 * of x,y corners.
 */
class PathController extends Controller
{
    public function __construct(private MapEditService $edits) {}

    public function store(Request $request, MallMap $mallMap): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'floor_code' => ['required', 'string', 'max:20'],
            'points' => ['required_without:corners', 'array', 'min:2', 'max:100'],
            'points.*' => ['array', 'size:2'],
            'points.*.*' => ['numeric', 'between:-10000,10000'],
            'corners' => ['required_without:points', 'nullable', 'string', 'max:4000'],
            'name' => ['nullable', 'string', 'max:120'],
            'step_free' => ['nullable', 'boolean'],
            'one_way' => ['nullable', 'boolean'],
        ]);
        $points = $data['points'] ?? $this->parseCorners((string) $data['corners']);

        try {
            $path = $this->edits->createPath($mallMap, $data['floor_code'], $points, $request->boolean('step_free', true), $request->boolean('one_way'), $data['name'] ?? null);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['path' => $error->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => self::present($mallMap, $path)], 201);
        }

        return back()->with('status', "Path {$path->key} added: {$path->length()} m with ".count($path->points).' corners.');
    }

    /**
     * Change a path; only the fields sent change (a rename sends only the name).
     */
    public function update(Request $request, MallMap $mallMap, MapPath $path): RedirectResponse|JsonResponse
    {
        abort_unless($path->mall_map_id === $mallMap->id, 404);
        $data = $request->validate([
            'floor_code' => ['sometimes', 'string', 'max:20'],
            'points' => ['sometimes', 'array', 'min:2', 'max:100'],
            'points.*' => ['array', 'size:2'],
            'points.*.*' => ['numeric', 'between:-10000,10000'],
            'corners' => ['sometimes', 'string', 'max:4000'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'step_free' => ['sometimes', 'boolean'],
            'one_way' => ['sometimes', 'boolean'],
        ]);
        $changes = array_intersect_key($data, array_flip(['floor_code', 'points', 'name']));
        if (isset($data['corners'])) {
            $changes['points'] = $this->parseCorners($data['corners']);
        }
        foreach (['step_free', 'one_way'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $changes[$flag] = $request->boolean($flag);
            }
        }

        try {
            $path = $this->edits->updatePath($path, $changes);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['path' => $error->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => self::present($mallMap, $path)])
            : back()->with('status', "Path {$path->key} saved.");
    }

    /**
     * Join another path that meets this one at an end into this one.
     */
    public function merge(Request $request, MallMap $mallMap, MapPath $path): RedirectResponse|JsonResponse
    {
        abort_unless($path->mall_map_id === $mallMap->id, 404);
        $data = $request->validate(['with' => ['required', 'string', 'max:16']]);
        $other = MapPath::query()->where('mall_map_id', $mallMap->id)->where('key', $data['with'])->first();
        if ($other === null) {
            throw ValidationException::withMessages(['with' => 'There is no path with that key on this map.']);
        }

        try {
            $path = $this->edits->mergePaths($path, $other);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['with' => $error->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => self::present($mallMap, $path)])
            : back()->with('status', "Path {$other->key} merged into {$path->key}: {$path->length()} m with ".count($path->points).' corners.');
    }

    public function destroy(Request $request, MallMap $mallMap, MapPath $path): RedirectResponse|JsonResponse
    {
        abort_unless($path->mall_map_id === $mallMap->id, 404);
        $this->edits->deletePath($path);

        return $request->wantsJson()
            ? response()->json(null, 204)
            : back()->with('status', "Path {$path->key} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(MallMap $map, MapPath $path): array
    {
        $own = DB::table('map_nodes')->where('map_path_id', $path->id)->pluck('id')->all();
        // Joined: some edge of the path (a walkway half included) touches a node the path did not make.
        $joined = DB::table('map_edges')->where('map_path_id', $path->id)
            ->where(fn ($q) => $q->whereNotIn('from_node_id', $own)->orWhereNotIn('to_node_id', $own))->exists();

        return [
            'key' => $path->key,
            'name' => $path->name,
            'floor_code' => $path->floor_code,
            'points' => $path->points,
            'corners' => count($path->points),
            'length_m' => $path->length(),
            'step_free' => $path->is_accessible,
            'one_way' => $path->one_way,
            'joined' => $joined,
            'problem' => $path->problem,
            'update_url' => route('admin.maps.paths.update', [$map, $path]),
            'merge_url' => route('admin.maps.paths.merge', [$map, $path]),
            'ends' => [array_values($path->points)[0], array_values($path->points)[count($path->points) - 1]],
        ];
    }

    /**
     * Corners written one per line as "x, y" (or "x y").
     *
     * @return list<array{0: float, 1: float}>
     */
    private function parseCorners(string $text): array
    {
        $points = [];
        foreach (preg_split('/\R/', trim($text)) ?: [] as $number => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $match)) {
                throw ValidationException::withMessages(['corners' => 'Line '.($number + 1).' is not “x, y” (metres), for example 2.5, 10.']);
            }
            $points[] = [(float) $match[1], (float) $match[2]];
        }

        return $points;
    }
}
