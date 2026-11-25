<?php

namespace App\Services;

use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Models\MapGraphEdit;
use App\Models\MapPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Administrators' edits on top of a map's own graph (ISM-114, decision D-022):
 *
 * - points: a plain node, a shop entrance, a facility or a QR code, joined to
 *   the nearest walkway of their floor (at most 6 m) through a junction;
 * - paths: walkways drawn corner by corner; a corner near an existing node or
 *   walkway joins it, the others become new nodes.
 *
 * Every edit has a unique key. Whatever an edit adds (nodes, edges, shop
 * entrance, facility and QR rows) carries its id, so the package's own graph
 * is never changed. Edits are applied again, in the order they were made,
 * after a graph rebuild, after an edit is deleted, and in a new map version
 * of the same mall (with the same keys). Edges added next to a walkway name it
 * as their parent and close with it.
 */
class MapEditService
{
    /** A point's junction this close to a walkway's end uses that end node instead. */
    private const POINT_SNAP_M = 0.25;

    /** A path corner this close to a node or walkway joins it. */
    private const PATH_SNAP_M = 0.5;

    private const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.\-]*$/';

    public function __construct(private CheckpointRegistry $checkpoints, private GraphBuilder $graph) {}

    // ------------------------------------------------------------------
    // Points
    // ------------------------------------------------------------------

    /**
     * Add a point: a node, a shop entrance, a facility or a QR code. For a QR
     * code, (x, y) is where it hangs and qr_yaw the direction its face points.
     * The height (of the point's marker, or of the QR code) is above the floor:
     * 1.5 m for a point and 1.4 m for a QR code unless another is given.
     *
     * @param  array<string, mixed>  $data  kind, name, floor_code, x, y and, by kind, height, unit_code, is_primary, facility_type, checkpoint_code, qr_yaw (qr_height and qr_z also set a QR code's height)
     *
     * @throws InvalidArgumentException when the point cannot be added
     */
    public function createPoint(MallMap $map, array $data): MapEndPoint
    {
        $fields = $this->pointFields($map, $data);

        return DB::transaction(function () use ($map, $fields): MapEndPoint {
            $point = MapEndPoint::query()->create($fields + [
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'sequence' => $this->nextSequence($map),
            ]);
            $this->applyPoint($point, $map, $this->settings($map));
            $this->syncCheckpoints($map);

            return $point->refresh();
        });
    }

    /**
     * Change a point: any of the fields createPoint takes; the others stay.
     * The key stays the same.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws InvalidArgumentException when the change is not possible
     */
    public function updatePoint(MapEndPoint $point, array $changes): MapEndPoint
    {
        $map = $point->mallMap;
        $current = [
            'kind' => $point->kind,
            'name' => $point->name,
            'floor_code' => $point->floor_code,
            'x' => $point->x_m,
            'y' => $point->y_m,
            'height' => $point->height_m,
            'unit_code' => $point->unit_code,
            'is_primary' => $point->is_primary,
            'facility_type' => $point->facility_type,
            'checkpoint_code' => $point->checkpoint_code,
            'qr_yaw' => $point->qr['yaw_deg'] ?? null,
        ];
        // A point that becomes a QR code, or stops being one, starts from that kind's default height.
        if (isset($changes['kind']) && ($changes['kind'] === 'checkpoint') !== ($point->kind === 'checkpoint') && ! isset($changes['height'])) {
            $current['height'] = null;
        }
        $fields = $this->pointFields($map, array_merge($current, $changes), $point);

        return DB::transaction(function () use ($point, $map, $fields): MapEndPoint {
            $point->forceFill($fields)->save();
            $this->applyAll($map);
            $point->refresh();
            if ($point->problem !== null) {
                throw new InvalidArgumentException($point->problem);
            }

            return $point;
        });
    }

    public function deletePoint(MapEndPoint $point): void
    {
        $map = $point->mallMap;
        DB::transaction(function () use ($point, $map): void {
            $point->delete();
            $this->applyAll($map);
        });
    }

    /**
     * Check a point's data and turn it into column values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function pointFields(MallMap $map, array $data, ?MapEndPoint $existing = null): array
    {
        $kind = (string) ($data['kind'] ?? '');
        if (! array_key_exists($kind, MapEndPoint::KINDS)) {
            throw new InvalidArgumentException('Choose what the point is: '.implode(', ', array_keys(MapEndPoint::KINDS)).'.');
        }
        $floorCode = (string) ($data['floor_code'] ?? '');
        $elevation = $this->elevation($map, $floorCode);
        if ($elevation === null) {
            throw new InvalidArgumentException("This map has no floor {$floorCode}.");
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Give the point a name of up to 120 characters.');
        }
        if (! is_numeric($data['x'] ?? null) || ! is_numeric($data['y'] ?? null)) {
            throw new InvalidArgumentException('Give the position as x and y in metres.');
        }
        $x = round((float) $data['x'], 3);
        $y = round((float) $data['y'], 3);
        $except = $existing?->id;

        $height = match (true) {
            $kind === 'checkpoint' && isset($data['qr_z']) => (float) $data['qr_z'] - $elevation,
            $kind === 'checkpoint' && isset($data['qr_height']) => (float) $data['qr_height'],
            isset($data['height']) => (float) $data['height'],
            default => $kind === 'checkpoint' ? MapEndPoint::DEFAULT_QR_HEIGHT_M : MapEndPoint::DEFAULT_HEIGHT_M,
        };
        if ($kind === 'checkpoint' && ($height < 0.2 || $height > 3.0)) {
            throw new InvalidArgumentException('A QR code hangs between 0.2 and 3 m above the floor.');
        }
        if ($height < 0 || $height > 10) {
            throw new InvalidArgumentException('A point\'s height is between 0 and 10 m above the floor.');
        }

        $fields = [
            'kind' => $kind,
            'name' => $name,
            'floor_code' => $floorCode,
            'x_m' => $x,
            'y_m' => $y,
            'z_m' => $elevation,
            'height_m' => round($height, 2),
            'unit_code' => null,
            'is_primary' => false,
            'facility_type' => null,
            'checkpoint_code' => null,
            'qr' => null,
        ];

        if ($kind === 'shop_entrance') {
            $unit = trim((string) ($data['unit_code'] ?? ''));
            if ($unit === '' || strlen($unit) > 40) {
                throw new InvalidArgumentException('Give the shop unit code, for example G-05.');
            }
            if (! empty($data['is_primary']) && $this->unitHasPrimary($map, $unit, $except)) {
                throw new InvalidArgumentException("Unit {$unit} already has a main door on this map. Untick “Main door”, or change the other one first.");
            }
            $fields['unit_code'] = $unit;
            $fields['is_primary'] = ! empty($data['is_primary']);
        } elseif ($kind === 'facility') {
            $type = trim((string) ($data['facility_type'] ?? ''));
            if (! preg_match('/^[a-z][a-z_]{0,39}$/', $type)) {
                throw new InvalidArgumentException('Give the facility type, for example toilet or prayer_room.');
            }
            if ($this->facilityExists($map, $name, $except)) {
                throw new InvalidArgumentException("A facility called “{$name}” is already on this map. Give this one another name.");
            }
            $fields['facility_type'] = $type;
        } elseif ($kind === 'checkpoint') {
            $code = trim((string) ($data['checkpoint_code'] ?? ''));
            if (strlen($code) > 60 || ! preg_match(self::CODE_PATTERN, $code)) {
                throw new InvalidArgumentException('Give the QR code a code such as G-C10 (letters, digits, dashes).');
            }
            if ($this->checkpointExists($map, $code, $except)) {
                throw new InvalidArgumentException("QR code {$code} is already on this map.");
            }
            $fields['checkpoint_code'] = $code;
            $fields['qr'] = [
                'x_m' => $x,
                'y_m' => $y,
                'z_m' => round($elevation + $height, 3),
                'yaw_deg' => round(fmod(fmod((float) ($data['qr_yaw'] ?? 0), 360.0) + 360.0, 360.0), 1),
                'marker_height_m' => round($height, 2),
                'physical_width_m' => PackagePresenter::QR_PRINT_WIDTH_M,
            ];
        }

        return $fields;
    }

    // ------------------------------------------------------------------
    // Paths
    // ------------------------------------------------------------------

    /**
     * Draw a walkway through the corners, in order, on one floor.
     *
     * @param  list<array{0: float, 1: float}>  $corners
     *
     * @throws InvalidArgumentException when the path cannot be added
     */
    public function createPath(MallMap $map, string $floorCode, array $corners, bool $stepFree = true, bool $oneWay = false, ?string $name = null): MapPath
    {
        $fields = $this->pathFields($map, ['floor_code' => $floorCode, 'points' => $corners, 'step_free' => $stepFree, 'one_way' => $oneWay, 'name' => $name]);

        return DB::transaction(function () use ($map, $fields): MapPath {
            $path = MapPath::query()->create($fields + [
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'sequence' => $this->nextSequence($map),
            ]);
            $this->applyPath($path, $map, $this->settings($map));
            if ($path->refresh()->problem !== null) {
                throw new InvalidArgumentException($path->problem);
            }

            return $path;
        });
    }

    /**
     * Change a path: its corners, floor, name, step-free or one-way. The key
     * stays the same.
     *
     * @param  array<string, mixed>  $changes  floor_code, points, step_free, one_way, name
     *
     * @throws InvalidArgumentException when the change is not possible
     */
    public function updatePath(MapPath $path, array $changes): MapPath
    {
        $map = $path->mallMap;
        $current = ['floor_code' => $path->floor_code, 'points' => $path->points, 'step_free' => $path->is_accessible, 'one_way' => $path->one_way, 'name' => $path->name];
        $fields = $this->pathFields($map, array_merge($current, $changes));

        return DB::transaction(function () use ($path, $map, $fields): MapPath {
            $path->forceFill($fields)->save();
            $this->applyAll($map);
            $path->refresh();
            if ($path->problem !== null) {
                throw new InvalidArgumentException($path->problem);
            }

            return $path;
        });
    }

    /**
     * Join two paths that meet at an end (ends at most 0.5 m apart) into the
     * first one, which keeps its key; the second is removed. The result is
     * step-free only if both were; one-way paths must run the same way.
     *
     * @throws InvalidArgumentException when they cannot be merged
     */
    public function mergePaths(MapPath $first, MapPath $second): MapPath
    {
        if ($first->mall_map_id !== $second->mall_map_id || $first->is($second)) {
            throw new InvalidArgumentException('Choose two different paths of the same map.');
        }
        if ($first->floor_code !== $second->floor_code) {
            throw new InvalidArgumentException('Paths on different floors cannot be merged.');
        }
        if ($first->one_way !== $second->one_way) {
            throw new InvalidArgumentException('Merge two one-way paths or two two-way paths, not one of each.');
        }

        $a = array_values($first->points);
        $b = array_values($second->points);
        $meet = fn (array $p, array $q): bool => hypot($p[0] - $q[0], $p[1] - $q[1]) <= self::PATH_SNAP_M;
        $reversed = false;
        if ($meet(end($a), $b[0])) {
            $corners = array_merge($a, array_slice($b, 1));
        } elseif ($meet($a[0], end($b))) {
            $corners = array_merge($b, array_slice($a, 1));
        } elseif ($meet(end($a), end($b))) {
            $corners = array_merge($a, array_slice(array_reverse($b), 1));
            $reversed = true;
        } elseif ($meet($a[0], $b[0])) {
            $corners = array_merge(array_reverse($b), array_slice($a, 1));
            $reversed = true;
        } else {
            throw new InvalidArgumentException('These paths do not meet at an end: their ends are more than 0.5 m apart.');
        }
        if ($reversed && $first->one_way) {
            throw new InvalidArgumentException('These one-way paths run against each other where they meet.');
        }
        if (count($corners) > 100) {
            throw new InvalidArgumentException('The merged path would have more than 100 corners.');
        }

        return DB::transaction(function () use ($first, $second, $corners): MapPath {
            $map = $first->mallMap;
            $first->forceFill([
                'points' => $corners,
                'is_accessible' => $first->is_accessible && $second->is_accessible,
                'name' => $first->name ?? $second->name,
            ])->save();
            $second->delete();
            $this->applyAll($map);
            $first->refresh();
            if ($first->problem !== null) {
                throw new InvalidArgumentException($first->problem);
            }

            return $first;
        });
    }

    public function deletePath(MapPath $path): void
    {
        $map = $path->mallMap;
        DB::transaction(function () use ($path, $map): void {
            $path->delete();
            $this->applyAll($map);
        });
    }

    /**
     * Check a path's data and turn it into column values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function pathFields(MallMap $map, array $data): array
    {
        $floorCode = (string) ($data['floor_code'] ?? '');
        if ($this->elevation($map, $floorCode) === null) {
            throw new InvalidArgumentException("This map has no floor {$floorCode}.");
        }
        $corners = [];
        foreach (array_values((array) ($data['points'] ?? [])) as $corner) {
            if (! is_array($corner) || ! is_numeric($corner[0] ?? null) || ! is_numeric($corner[1] ?? null)) {
                throw new InvalidArgumentException('Each corner is x and y in metres.');
            }
            $corners[] = [round((float) $corner[0], 3), round((float) $corner[1], 3)];
        }
        if (count($corners) < 2 || count($corners) > 100) {
            throw new InvalidArgumentException('A path needs 2 to 100 corners.');
        }
        for ($i = 1; $i < count($corners); $i++) {
            $segment = hypot($corners[$i][0] - $corners[$i - 1][0], $corners[$i][1] - $corners[$i - 1][1]);
            if ($segment < 0.05) {
                throw new InvalidArgumentException('Two corners of the path are in the same place.');
            }
            if ($segment > 200) {
                throw new InvalidArgumentException('A stretch of the path is longer than 200 m; add corners in between.');
            }
        }
        $name = isset($data['name']) ? trim((string) $data['name']) : '';

        return [
            'floor_code' => $floorCode,
            'points' => $corners,
            'is_accessible' => (bool) ($data['step_free'] ?? true),
            'one_way' => (bool) ($data['one_way'] ?? false),
            'name' => $name === '' ? null : mb_substr($name, 0, 120),
        ];
    }

    // ------------------------------------------------------------------
    // Changes to the navigation graph itself
    // ------------------------------------------------------------------

    /**
     * Change the navigation graph: move or remove a node, connect two nodes,
     * change or remove a walkway. Nodes are named by code.
     *
     * @param  array<string, mixed>  $data  action and node_code (x, y) or from_code, to_code (step_free, one_way, direction)
     *
     * @throws InvalidArgumentException when the change is not possible
     */
    public function createGraphEdit(MallMap $map, array $data): MapGraphEdit
    {
        $fields = $this->graphEditFields($data);

        return DB::transaction(function () use ($map, $fields): MapGraphEdit {
            $edit = MapGraphEdit::query()->create($fields + [
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'sequence' => $this->nextSequence($map),
            ]);
            $this->applyGraphEdit($edit, $map, $this->settings($map));
            $this->syncCheckpoints($map);
            if ($edit->refresh()->problem !== null) {
                throw new InvalidArgumentException($edit->problem);
            }

            return $edit;
        });
    }

    /**
     * Edit a change to the graph: other nodes, another position, other
     * walkway settings. What it does (move, remove, connect …), its key and
     * its place in the order of edits stay; the graph is replayed.
     *
     * @param  array<string, mixed>  $changes  node_code, x, y, from_code, to_code, step_free, one_way, direction; for a walkway change, a null step_free or direction leaves that alone
     *
     * @throws InvalidArgumentException when the change is not possible
     */
    public function updateGraphEdit(MapGraphEdit $edit, array $changes): MapGraphEdit
    {
        $map = $edit->mallMap;
        $current = ['node_code' => $edit->node_code, 'from_code' => $edit->from_code, 'to_code' => $edit->to_code] + ($edit->data ?? []);
        $fields = $this->graphEditFields(['action' => $edit->action] + array_merge($current, $changes));

        try {
            return DB::transaction(function () use ($edit, $map, $fields): MapGraphEdit {
                $edit->forceFill($fields)->save();
                $this->applyAll($map);
                $edit->refresh();
                if ($edit->problem !== null) {
                    throw new InvalidArgumentException($edit->problem);
                }

                return $edit;
            });
        } catch (InvalidArgumentException $error) {
            // The transaction was rolled back; the model must not keep the refused values.
            $edit->refresh();
            throw $error;
        }
    }

    /**
     * Undo a change to the graph.
     */
    public function deleteGraphEdit(MapGraphEdit $edit): void
    {
        $map = $edit->mallMap;
        DB::transaction(function () use ($edit, $map): void {
            $edit->delete();
            $this->applyAll($map);
        });
    }

    /**
     * Check the form of a change to the graph and turn it into column values.
     * Whether it fits the graph is checked when it is applied.
     *
     * @param  array<string, mixed>  $data
     * @return array{action: string, node_code: string|null, from_code: string|null, to_code: string|null, data: array<string, mixed>|null}
     *
     * @throws InvalidArgumentException
     */
    private function graphEditFields(array $data): array
    {
        $action = (string) ($data['action'] ?? '');
        if (! in_array($action, MapGraphEdit::ACTIONS, true)) {
            throw new InvalidArgumentException('Choose a change: '.implode(', ', MapGraphEdit::ACTIONS).'.');
        }
        $code = fn (string $name): string => trim((string) ($data[$name] ?? ''));
        $fields = ['action' => $action, 'node_code' => null, 'from_code' => null, 'to_code' => null, 'data' => null];

        if (in_array($action, ['move_node', 'remove_node'], true)) {
            if ($code('node_code') === '') {
                throw new InvalidArgumentException('Give the code of the node.');
            }
            $fields['node_code'] = $code('node_code');
            if ($action === 'move_node') {
                if (! is_numeric($data['x'] ?? null) || ! is_numeric($data['y'] ?? null)) {
                    throw new InvalidArgumentException('Give the new position as x and y in metres.');
                }
                $fields['data'] = ['x' => round((float) $data['x'], 3), 'y' => round((float) $data['y'], 3)];
            }

            return $fields;
        }

        if ($code('from_code') === '' || $code('to_code') === '') {
            throw new InvalidArgumentException('Give the codes of both nodes.');
        }
        if ($code('from_code') === $code('to_code')) {
            throw new InvalidArgumentException('Choose two different nodes.');
        }
        $fields['from_code'] = $code('from_code');
        $fields['to_code'] = $code('to_code');
        if ($action === 'connect') {
            $fields['data'] = ['step_free' => (bool) ($data['step_free'] ?? true), 'one_way' => (bool) ($data['one_way'] ?? false)];
        } elseif ($action === 'edit_edge') {
            $changes = array_filter([
                'step_free' => isset($data['step_free']) ? (bool) $data['step_free'] : null,
                'direction' => in_array($data['direction'] ?? null, ['both', 'forward', 'backward'], true) ? $data['direction'] : null,
            ], fn (mixed $value): bool => $value !== null);
            if ($changes === []) {
                throw new InvalidArgumentException('Say what changes: step-free, or the direction (both, forward, backward).');
            }
            $fields['data'] = $changes;
        }

        return $fields;
    }

    /**
     * Apply one change to the graph as it is now. When it does not fit, the
     * graph stays as it was and the change keeps the reason as its problem.
     *
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function applyGraphEdit(MapGraphEdit $edit, MallMap $map, array $settings): void
    {
        $nodes = DB::table('map_nodes')->where('mall_map_id', $map->id)->get()->keyBy('node_code');
        $problem = null;

        if (in_array($edit->action, ['move_node', 'remove_node'], true)) {
            $node = $nodes[$edit->node_code] ?? null;
            if ($node === null) {
                $problem = "Node {$edit->node_code} is not on this map.";
            } elseif ($node->map_end_point_id !== null) {
                $problem = "Node {$edit->node_code} belongs to a point; edit or delete the point instead.";
            } elseif ($edit->action === 'move_node') {
                $this->moveNode($map, $node, (float) $edit->data['x'], (float) $edit->data['y'], $settings);
            } elseif (($why = $this->nodeHolds($map, (int) $node->id)) !== null) {
                $problem = "Node {$edit->node_code} {$why}; remove that first.";
            } else {
                DB::table('map_nodes')->where('id', $node->id)->delete();
                $this->removeOrphanHalves($map);
            }
        } else {
            $from = $nodes[$edit->from_code] ?? null;
            $to = $nodes[$edit->to_code] ?? null;
            if ($from === null || $to === null) {
                $problem = 'Node '.($from === null ? $edit->from_code : $edit->to_code).' is not on this map.';
            } else {
                $problem = match ($edit->action) {
                    'connect' => $this->connectNodes($map, $from, $to, $edit->data ?? [], $settings),
                    'edit_edge' => $this->changeWalkway($map, $from, $to, $edit->data ?? []),
                    'remove_edge' => $this->removeWalkway($map, $from, $to),
                    default => "Unknown change {$edit->action}.",
                };
            }
        }

        $edit->forceFill(['problem' => $problem])->save();
    }

    /**
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function moveNode(MallMap $map, object $node, float $x, float $y, array $settings): void
    {
        DB::table('map_nodes')->where('id', $node->id)->update(['x_m' => $x, 'y_m' => $y]);
        $positions = DB::table('map_nodes')->where('mall_map_id', $map->id)->get(['id', 'x_m', 'y_m'])->keyBy('id');
        $edges = DB::table('map_edges')->where('mall_map_id', $map->id)->whereNull('map_connector_id')
            ->where(fn ($q) => $q->where('from_node_id', $node->id)->orWhere('to_node_id', $node->id))->get();
        foreach ($edges as $edge) {
            $a = $positions[$edge->from_node_id];
            $b = $positions[$edge->to_node_id];
            $length = max(0.01, hypot($b->x_m - $a->x_m, $b->y_m - $a->y_m));
            $pace = $edge->length_m > 0 ? $edge->expected_seconds / $edge->length_m : 1 / $settings['walk'];
            DB::table('map_edges')->where('id', $edge->id)->update([
                'length_m' => round($length, 3),
                'expected_seconds' => round(max(0.01, $length * $pace), 2),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function connectNodes(MallMap $map, object $from, object $to, array $data, array $settings): ?string
    {
        if ($from->floor_code !== $to->floor_code) {
            return "A walkway joins two nodes of the same floor ({$from->node_code} is on floor {$from->floor_code}, {$to->node_code} on floor {$to->floor_code}); floors are joined by lifts, escalators and stairs in the map package.";
        }
        $length = round(max(0.01, hypot($to->x_m - $from->x_m, $to->y_m - $from->y_m)), 3);
        $added = 0;
        foreach (! empty($data['one_way']) ? [[$from->id, $to->id]] : [[$from->id, $to->id], [$to->id, $from->id]] as [$a, $b]) {
            if (DB::table('map_edges')->where('mall_map_id', $map->id)->where('from_node_id', $a)->where('to_node_id', $b)->exists()) {
                continue;
            }
            DB::table('map_edges')->insert([
                'mall_map_id' => $map->id,
                'from_node_id' => $a,
                'to_node_id' => $b,
                'length_m' => $length,
                'expected_seconds' => round(max(0.01, $length / $settings['walk']), 2),
                'is_accessible' => (bool) ($data['step_free'] ?? true),
                'status' => 'open',
            ]);
            $added++;
        }

        return $added === 0 ? "{$from->node_code} and {$to->node_code} are already joined by a walkway." : null;
    }

    /**
     * @param  array<string, mixed>  $data  step_free and/or direction
     */
    private function changeWalkway(MallMap $map, object $from, object $to, array $data): ?string
    {
        $edges = $this->edgesBetween($map, (int) $from->id, (int) $to->id);
        if ($edges === []) {
            return "No walkway joins {$from->node_code} and {$to->node_code}.";
        }
        if (isset($data['step_free'])) {
            $ids = $this->withHalves($map, array_map(fn (object $edge): int => (int) $edge->id, $edges));
            DB::table('map_edges')->whereIn('id', $ids)->update(['is_accessible' => (bool) $data['step_free']]);
        }
        $direction = $data['direction'] ?? null;
        if ($direction !== null) {
            $forward = $this->edgeFromTo($map, (int) $from->id, (int) $to->id);
            $backward = $this->edgeFromTo($map, (int) $to->id, (int) $from->id);
            $copy = fn (object $source, int $a, int $b) => DB::table('map_edges')->insert([
                'mall_map_id' => $map->id,
                'from_node_id' => $a,
                'to_node_id' => $b,
                'length_m' => $source->length_m,
                'expected_seconds' => $source->expected_seconds,
                'is_accessible' => $source->is_accessible,
                'status' => 'open',
                'map_connector_id' => $source->map_connector_id,
            ]);
            if ($direction !== 'backward' && $forward === null) {
                $copy($backward, (int) $from->id, (int) $to->id);
            }
            if ($direction !== 'forward' && $backward === null) {
                $copy($forward, (int) $to->id, (int) $from->id);
            }
            if ($direction === 'forward' && $backward !== null) {
                $this->deleteEdges($map, [(int) $backward->id]);
            }
            if ($direction === 'backward' && $forward !== null) {
                $this->deleteEdges($map, [(int) $forward->id]);
            }
        }

        return null;
    }

    private function removeWalkway(MallMap $map, object $from, object $to): ?string
    {
        $edges = $this->edgesBetween($map, (int) $from->id, (int) $to->id);
        if ($edges === []) {
            return "No walkway joins {$from->node_code} and {$to->node_code}.";
        }
        $this->deleteEdges($map, array_map(fn (object $edge): int => (int) $edge->id, $edges));

        return null;
    }

    /**
     * Why a node cannot be removed (a door, facility, QR code, lift stop or
     * point stands on it), or null.
     */
    private function nodeHolds(MallMap $map, int $nodeId): ?string
    {
        return match (true) {
            DB::table('map_shop_entrances')->where('map_node_id', $nodeId)->exists() => 'is a shop entrance',
            DB::table('map_facility_anchors')->where('map_node_id', $nodeId)->exists() => 'is a facility',
            DB::table('map_checkpoint_anchors')->where('map_node_id', $nodeId)->exists() => 'is where visitors stand at a QR code',
            DB::table('map_connector_stops')->where('map_node_id', $nodeId)->exists() => 'is a stop of a lift, escalator or stairs',
            MapEndPoint::query()->where('mall_map_id', $map->id)->where('map_node_id', $nodeId)->exists() => 'belongs to a point',
            default => null,
        };
    }

    /**
     * @return list<object> the edges joining two nodes, either way
     */
    private function edgesBetween(MallMap $map, int $a, int $b): array
    {
        return DB::table('map_edges')->where('mall_map_id', $map->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('from_node_id', $a)->where('to_node_id', $b))->orWhere(fn ($w) => $w->where('from_node_id', $b)->where('to_node_id', $a)))
            ->get()->all();
    }

    private function edgeFromTo(MallMap $map, int $a, int $b): ?object
    {
        return DB::table('map_edges')->where('mall_map_id', $map->id)->where('from_node_id', $a)->where('to_node_id', $b)->first();
    }

    /**
     * The edges and every half added on them, at any depth.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function withHalves(MallMap $map, array $ids): array
    {
        $all = $ids;
        $frontier = $ids;
        while ($frontier !== []) {
            $frontier = DB::table('map_edges')->where('mall_map_id', $map->id)->whereIn('parent_edge_id', $frontier)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique($all));
    }

    /**
     * Delete edges and the halves added on them, so a removed walkway cannot
     * still be used through a junction.
     *
     * @param  list<int>  $ids
     */
    private function deleteEdges(MallMap $map, array $ids): void
    {
        DB::table('map_edges')->whereIn('id', $this->withHalves($map, $ids))->delete();
    }

    /**
     * Delete halves whose parent walkway is gone (after a node was removed).
     */
    private function removeOrphanHalves(MallMap $map): void
    {
        do {
            $orphans = DB::table('map_edges as h')->where('h.mall_map_id', $map->id)->whereNotNull('h.parent_edge_id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('map_edges as p')->whereColumn('p.id', 'h.parent_edge_id'))
                ->pluck('h.id')->all();
            if ($orphans !== []) {
                DB::table('map_edges')->whereIn('id', $orphans)->delete();
            }
        } while ($orphans !== []);
    }

    // ------------------------------------------------------------------
    // Applying edits
    // ------------------------------------------------------------------

    /**
     * Write the map's graph from its package again and replay every edit
     * (points, paths and changes to the graph) in the order it was made,
     * keeping the closures of edges that still exist.
     *
     * @param  array<string, mixed>|null  $package  the package to build from; the stored one when null
     */
    public function applyAll(MallMap $map, ?array $package = null): void
    {
        DB::transaction(function () use ($map, $package): void {
            $closed = $this->closedEdges($map);
            $this->graph->build($map, $package ?? $map->package());
            MapEndPoint::query()->where('mall_map_id', $map->id)->update(['map_node_id' => null, 'link_m' => null]);
            $settings = $this->settings($map);

            $edits = collect()
                ->concat(MapEndPoint::query()->where('mall_map_id', $map->id)->get())
                ->concat(MapPath::query()->where('mall_map_id', $map->id)->get())
                ->concat(MapGraphEdit::query()->where('mall_map_id', $map->id)->get())
                ->sortBy(fn (MapEndPoint|MapPath|MapGraphEdit $edit): string => sprintf('%012d-%d-%012d', $edit->sequence, match (true) {
                    $edit instanceof MapEndPoint => 0,
                    $edit instanceof MapPath => 1,
                    default => 2,
                }, $edit->id));
            foreach ($edits as $edit) {
                match (true) {
                    $edit instanceof MapEndPoint => $this->applyPoint($edit, $map, $settings),
                    $edit instanceof MapPath => $this->applyPath($edit, $map, $settings),
                    default => $this->applyGraphEdit($edit, $map, $settings),
                };
            }

            $this->restoreClosures($map, $closed);
            $this->syncCheckpoints($map);
        });
    }

    /**
     * Rebuild a stored map's graph (map:rebuild-graph): the package's graph
     * plus every edit.
     */
    public function rebuild(MallMap $map): void
    {
        $this->applyAll($map);
    }

    /**
     * Copy every edit of another map of the same mall into a new map version,
     * with the same keys, and apply them there.
     */
    public function copyInto(MallMap $from, MallMap $to): int
    {
        $copied = 0;
        foreach (MapEndPoint::query()->where('mall_map_id', $from->id)->get() as $point) {
            MapEndPoint::query()->create([
                'mall_map_id' => $to->id,
                'key' => $point->key,
                'kind' => $point->kind,
                'sequence' => $point->sequence,
                'name' => $point->name,
                'floor_code' => $point->floor_code,
                'x_m' => $point->x_m,
                'y_m' => $point->y_m,
                'z_m' => $this->elevation($to, $point->floor_code) ?? $point->z_m,
                'unit_code' => $point->unit_code,
                'is_primary' => $point->is_primary,
                'facility_type' => $point->facility_type,
                'checkpoint_code' => $point->checkpoint_code,
                'qr' => $point->qr,
                'height_m' => $point->height_m,
            ]);
            $copied++;
        }
        foreach (MapGraphEdit::query()->where('mall_map_id', $from->id)->get() as $graphEdit) {
            MapGraphEdit::query()->create([
                'mall_map_id' => $to->id,
                'key' => $graphEdit->key,
                'sequence' => $graphEdit->sequence,
                'action' => $graphEdit->action,
                'node_code' => $graphEdit->node_code,
                'from_code' => $graphEdit->from_code,
                'to_code' => $graphEdit->to_code,
                'data' => $graphEdit->data,
            ]);
            $copied++;
        }
        foreach (MapPath::query()->where('mall_map_id', $from->id)->get() as $path) {
            MapPath::query()->create([
                'mall_map_id' => $to->id,
                'key' => $path->key,
                'sequence' => $path->sequence,
                'name' => $path->name,
                'floor_code' => $path->floor_code,
                'points' => $path->points,
                'is_accessible' => $path->is_accessible,
                'one_way' => $path->one_way,
            ]);
            $copied++;
        }
        if ($copied > 0) {
            $this->applyAll($to);
        }

        return $copied;
    }

    /**
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function applyPoint(MapEndPoint $point, MallMap $map, array $settings): void
    {
        $elevation = $this->elevation($map, $point->floor_code);
        $problem = match (true) {
            $elevation === null => "This map has no floor {$point->floor_code}.",
            $point->kind === 'facility' && $this->facilityExists($map, $point->name, $point->id) => "A facility called “{$point->name}” is already on this map.",
            $point->kind === 'checkpoint' && $this->checkpointExists($map, (string) $point->checkpoint_code, $point->id) => "QR code {$point->checkpoint_code} is already on this map.",
            default => null,
        };
        if ($problem !== null) {
            $point->forceFill(['problem' => $problem, 'map_node_id' => null, 'link_m' => null])->save();

            return;
        }

        [$x, $y] = $point->nodePosition($settings['standoff']);
        $owner = ['mall_map_id' => $map->id, 'map_end_point_id' => $point->id];
        $nodeId = DB::table('map_nodes')->insertGetId($owner + [
            'floor_code' => $point->floor_code,
            'node_code' => 'P-'.$point->key,
            'node_type' => MapEndPoint::NODE_TYPES[$point->kind],
            'x_m' => $x,
            'y_m' => $y,
            'z_m' => (float) $elevation,
            'is_accessible' => true,
        ]);

        $link = $this->joinToWalkway($map, $point->floor_code, $x, $y, (float) $elevation, $nodeId, $owner, 'P-'.$point->key.'-J', $settings);

        if ($point->kind === 'shop_entrance') {
            DB::table('map_shop_entrances')->insert($owner + [
                'unit_code' => $point->unit_code,
                'map_node_id' => $nodeId,
                'is_primary' => $point->is_primary && ! $this->unitHasPrimary($map, (string) $point->unit_code, $point->id),
            ]);
        } elseif ($point->kind === 'facility') {
            DB::table('map_facility_anchors')->insert($owner + [
                'facility_name' => $point->name,
                'facility_type' => $point->facility_type,
                'map_node_id' => $nodeId,
            ]);
        } elseif ($point->kind === 'checkpoint') {
            DB::table('map_checkpoint_anchors')->insert($owner + [
                'checkpoint_code' => $point->checkpoint_code,
                'label' => mb_substr($point->name, 0, 160),
                'map_node_id' => $nodeId,
                'qr' => json_encode($point->qr, JSON_THROW_ON_ERROR),
            ]);
        }

        $point->forceFill(['map_node_id' => $nodeId, 'link_m' => $link, 'problem' => null])->save();
    }

    /**
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function applyPath(MapPath $path, MallMap $map, array $settings): void
    {
        $elevation = $this->elevation($map, $path->floor_code);
        if ($elevation === null) {
            $path->forceFill(['problem' => "This map has no floor {$path->floor_code}."])->save();

            return;
        }

        $owner = ['mall_map_id' => $map->id, 'map_path_id' => $path->id];
        $nodeIds = [];
        foreach (array_values($path->points) as $index => [$x, $y]) {
            $nodeIds[] = $this->cornerNode($map, $path, (float) $x, (float) $y, (float) $elevation, $owner, 'W-'.$path->key.'-'.($index + 1));
        }

        $positions = DB::table('map_nodes')->whereIn('id', array_unique($nodeIds))->get()->keyBy('id');
        $added = 0;
        for ($i = 1; $i < count($nodeIds); $i++) {
            [$from, $to] = [$nodeIds[$i - 1], $nodeIds[$i]];
            if ($from === $to) {
                continue;
            }
            $length = round(max(0.01, hypot($positions[$to]->x_m - $positions[$from]->x_m, $positions[$to]->y_m - $positions[$from]->y_m)), 3);
            foreach ($path->one_way ? [[$from, $to]] : [[$from, $to], [$to, $from]] as [$a, $b]) {
                if (DB::table('map_edges')->where('mall_map_id', $map->id)->where('from_node_id', $a)->where('to_node_id', $b)->exists()) {
                    continue;
                }
                DB::table('map_edges')->insert($owner + [
                    'from_node_id' => $a,
                    'to_node_id' => $b,
                    'length_m' => $length,
                    'expected_seconds' => round(max(0.01, $length / $settings['walk']), 2),
                    'is_accessible' => $path->is_accessible,
                    'status' => 'open',
                ]);
                $added++;
            }
        }

        $path->forceFill(['problem' => $added === 0 ? 'The path adds no walkway: its corners fall on places that are already joined.' : null])->save();
    }

    /**
     * The node for a path corner: an existing node within 0.5 m, or a new
     * junction on a walkway within 0.5 m, or a new node.
     *
     * @param  array<string, mixed>  $owner
     */
    private function cornerNode(MallMap $map, MapPath $path, float $x, float $y, float $elevation, array $owner, string $code): int
    {
        $nearest = null;
        foreach (DB::table('map_nodes')->where('mall_map_id', $map->id)->where('floor_code', $path->floor_code)->get(['id', 'x_m', 'y_m']) as $node) {
            $distance = hypot($node->x_m - $x, $node->y_m - $y);
            if ($distance <= self::PATH_SNAP_M && ($nearest === null || $distance < $nearest[1])) {
                $nearest = [(int) $node->id, $distance];
            }
        }
        if ($nearest !== null) {
            return $nearest[0];
        }

        $walkway = $this->nearestWalkway($map, $path->floor_code, $x, $y, excludePath: $path->id);
        if ($walkway !== null && $walkway['distance'] <= self::PATH_SNAP_M) {
            return $this->splitWalkway($walkway, $elevation, $owner, $code);
        }

        return DB::table('map_nodes')->insertGetId($owner + [
            'floor_code' => $path->floor_code,
            'node_code' => $code,
            'node_type' => 'junction',
            'x_m' => $x,
            'y_m' => $y,
            'z_m' => $elevation,
            'is_accessible' => true,
        ]);
    }

    /**
     * Join a new node to the nearest walkway of its floor within the link
     * limit. Returns the distance, or null when no walkway is near enough
     * (the node then waits for a path).
     *
     * @param  array<string, mixed>  $owner
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function joinToWalkway(MallMap $map, string $floorCode, float $x, float $y, float $elevation, int $nodeId, array $owner, string $junctionCode, array $settings): ?float
    {
        $walkway = $this->nearestWalkway($map, $floorCode, $x, $y, excludeNode: $nodeId);
        if ($walkway === null || $walkway['distance'] > $settings['link_max']) {
            return null;
        }

        if ($walkway['along_a'] <= self::POINT_SNAP_M) {
            $junctionId = (int) $walkway['a']->id;
        } elseif ($walkway['along_b'] <= self::POINT_SNAP_M) {
            $junctionId = (int) $walkway['b']->id;
        } else {
            $junctionId = $this->splitWalkway($walkway, $elevation, $owner, $junctionCode);
        }

        $length = round(max(0.01, $walkway['distance']), 3);
        foreach ([[$junctionId, $nodeId], [$nodeId, $junctionId]] as [$from, $to]) {
            DB::table('map_edges')->insert($owner + [
                'from_node_id' => $from,
                'to_node_id' => $to,
                'length_m' => $length,
                'expected_seconds' => round(max(0.01, $length / $settings['walk']), 2),
                'is_accessible' => true,
                'status' => 'open',
            ]);
        }

        return round($walkway['distance'], 2);
    }

    /**
     * The walkway (both directions of a node pair, connectors left out) of a
     * floor closest to (x, y), with the closest point on it. Edges a point
     * added are left out; edges of paths count.
     *
     * @return array{distance: float, a: object, b: object, qx: float, qy: float, along_a: float, along_b: float, edges: list<object>, nodes: Collection<int, object>}|null
     */
    private function nearestWalkway(MallMap $map, string $floorCode, float $x, float $y, ?int $excludeNode = null, ?int $excludePath = null): ?array
    {
        $nodes = DB::table('map_nodes')->where('mall_map_id', $map->id)->where('floor_code', $floorCode)->get()->keyBy('id');
        $pairs = [];
        $edges = DB::table('map_edges')->where('mall_map_id', $map->id)->whereNull('map_connector_id')->whereNull('map_end_point_id')
            ->when($excludePath !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('map_path_id')->orWhere('map_path_id', '!=', $excludePath)))
            ->get();
        foreach ($edges as $edge) {
            if (! isset($nodes[$edge->from_node_id], $nodes[$edge->to_node_id]) || in_array($excludeNode, [$edge->from_node_id, $edge->to_node_id], true)) {
                continue;
            }
            $pairs[min($edge->from_node_id, $edge->to_node_id).'-'.max($edge->from_node_id, $edge->to_node_id)][] = $edge;
        }

        $best = null;
        foreach ($pairs as $pair) {
            $a = $nodes[min($pair[0]->from_node_id, $pair[0]->to_node_id)];
            $b = $nodes[max($pair[0]->from_node_id, $pair[0]->to_node_id)];
            $dx = $b->x_m - $a->x_m;
            $dy = $b->y_m - $a->y_m;
            $lengthSquared = $dx * $dx + $dy * $dy;
            $t = $lengthSquared < 1e-12 ? 0.0 : max(0.0, min(1.0, (($x - $a->x_m) * $dx + ($y - $a->y_m) * $dy) / $lengthSquared));
            $qx = $a->x_m + $t * $dx;
            $qy = $a->y_m + $t * $dy;
            $distance = hypot($x - $qx, $y - $qy);
            if ($best === null || $distance < $best['distance'] - 1e-9) {
                $best = [
                    'distance' => $distance,
                    'a' => $a,
                    'b' => $b,
                    'qx' => $qx,
                    'qy' => $qy,
                    'along_a' => hypot($qx - $a->x_m, $qy - $a->y_m),
                    'along_b' => hypot($qx - $b->x_m, $qy - $b->y_m),
                    'edges' => $pair,
                    'nodes' => $nodes,
                ];
            }
        }

        return $best;
    }

    /**
     * Put a junction on a walkway: a new node and, for each direction of the
     * walkway, two half edges whose parent is that direction.
     *
     * @param  array<string, mixed>  $walkway  from nearestWalkway()
     * @param  array<string, mixed>  $owner
     */
    private function splitWalkway(array $walkway, float $elevation, array $owner, string $code): int
    {
        $floorCode = $walkway['a']->floor_code;
        $junctionId = DB::table('map_nodes')->insertGetId($owner + [
            'floor_code' => $floorCode,
            'node_code' => $code,
            'node_type' => 'junction',
            'x_m' => round($walkway['qx'], 3),
            'y_m' => round($walkway['qy'], 3),
            'z_m' => $elevation,
            'is_accessible' => true,
        ]);
        foreach ($walkway['edges'] as $parent) {
            $from = $walkway['nodes'][$parent->from_node_id];
            $to = $walkway['nodes'][$parent->to_node_id];
            $first = hypot($walkway['qx'] - $from->x_m, $walkway['qy'] - $from->y_m);
            $second = hypot($walkway['qx'] - $to->x_m, $walkway['qy'] - $to->y_m);
            foreach ([[$parent->from_node_id, $junctionId, $first], [$junctionId, $parent->to_node_id, $second]] as [$fromId, $toId, $straight]) {
                $share = $straight / max($first + $second, 1e-6);
                DB::table('map_edges')->insert($owner + [
                    'from_node_id' => $fromId,
                    'to_node_id' => $toId,
                    'length_m' => round(max(0.01, $parent->length_m * $share), 3),
                    'expected_seconds' => round(max(0.01, $parent->expected_seconds * $share), 2),
                    'is_accessible' => (bool) $parent->is_accessible,
                    'status' => 'open',
                    'parent_edge_id' => $parent->id,
                ]);
            }
        }

        return $junctionId;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{status: string, reason: string|null, changed_at: string|null}>
     */
    private function closedEdges(MallMap $map): array
    {
        return DB::table('map_edges as e')
            ->join('map_nodes as a', 'a.id', '=', 'e.from_node_id')
            ->join('map_nodes as b', 'b.id', '=', 'e.to_node_id')
            ->where('e.mall_map_id', $map->id)->where('e.status', 'closed')
            ->get(['a.node_code as from_code', 'b.node_code as to_code', 'e.closed_reason', 'e.status_changed_at'])
            ->mapWithKeys(fn (object $edge): array => [$edge->from_code.'|'.$edge->to_code => ['status' => 'closed', 'reason' => $edge->closed_reason, 'changed_at' => $edge->status_changed_at]])
            ->all();
    }

    /**
     * @param  array<string, array{status: string, reason: string|null, changed_at: string|null}>  $closed
     */
    private function restoreClosures(MallMap $map, array $closed): void
    {
        if ($closed === []) {
            return;
        }
        $codes = DB::table('map_nodes')->where('mall_map_id', $map->id)->pluck('id', 'node_code');
        foreach ($closed as $pair => $status) {
            [$from, $to] = explode('|', $pair, 2);
            if (isset($codes[$from], $codes[$to])) {
                DB::table('map_edges')->where('mall_map_id', $map->id)->where('from_node_id', $codes[$from])->where('to_node_id', $codes[$to])
                    ->update(['status' => 'closed', 'closed_reason' => $status['reason'], 'status_changed_at' => $status['changed_at']]);
            }
        }
    }

    private function syncCheckpoints(MallMap $map): void
    {
        $fresh = MallMap::query()->find($map->id);
        if ($fresh !== null && $fresh->mall_ref !== null && $fresh->isPublished()) {
            $this->checkpoints->syncFromMap($fresh);
        }
    }

    /**
     * @return array{walk: float, standoff: float, link_max: float}
     */
    private function settings(MallMap $map): array
    {
        $settings = $map->package()['settings'] ?? [];

        return [
            'walk' => max(0.1, (float) ($settings['ism_walk_mps'] ?? 1.2)),
            'standoff' => (float) ($settings['ism_checkpoint_standoff_m'] ?? 0.8),
            'link_max' => (float) config('mapservice.end_point_link_max_m', 6.0),
        ];
    }

    private function elevation(MallMap $map, string $floorCode): ?float
    {
        $elevation = DB::table('map_floors')->where('mall_map_id', $map->id)->where('floor_code', $floorCode)->value('elevation_m');

        return $elevation === null ? null : (float) $elevation;
    }

    private function unitHasPrimary(MallMap $map, string $unitCode, ?int $exceptPoint = null): bool
    {
        return DB::table('map_shop_entrances')->where('mall_map_id', $map->id)->where('unit_code', $unitCode)->where('is_primary', true)
            ->when($exceptPoint !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('map_end_point_id')->orWhere('map_end_point_id', '!=', $exceptPoint)))
            ->exists();
    }

    private function facilityExists(MallMap $map, string $name, ?int $exceptPoint = null): bool
    {
        return DB::table('map_facility_anchors')->where('mall_map_id', $map->id)->where('facility_name', $name)
            ->when($exceptPoint !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('map_end_point_id')->orWhere('map_end_point_id', '!=', $exceptPoint)))
            ->exists();
    }

    private function checkpointExists(MallMap $map, string $code, ?int $exceptPoint = null): bool
    {
        return DB::table('map_checkpoint_anchors')->where('mall_map_id', $map->id)->where('checkpoint_code', $code)
            ->when($exceptPoint !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('map_end_point_id')->orWhere('map_end_point_id', '!=', $exceptPoint)))
            ->exists()
            || MapEndPoint::query()->where('mall_map_id', $map->id)->where('checkpoint_code', $code)
                ->when($exceptPoint !== null, fn ($query) => $query->whereKeyNot($exceptPoint))
                ->whereNotNull('map_node_id')->exists();
    }

    private function nextSequence(MallMap $map): int
    {
        return 1 + max(
            (int) MapEndPoint::query()->where('mall_map_id', $map->id)->max('sequence'),
            (int) MapPath::query()->where('mall_map_id', $map->id)->max('sequence'),
            (int) MapGraphEdit::query()->where('mall_map_id', $map->id)->max('sequence'),
        );
    }

    /**
     * A key no point or path of any map has: 10 lower-case letters and digits.
     */
    private function newKey(): string
    {
        do {
            $key = Str::lower(Str::random(10));
        } while (MapEndPoint::query()->where('key', $key)->exists() || MapPath::query()->where('key', $key)->exists() || MapGraphEdit::query()->where('key', $key)->exists());

        return $key;
    }
}
