<?php

namespace App\Services;

use App\Models\MallMap;
use App\Models\MapEndPoint;
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

    public function __construct(private CheckpointRegistry $checkpoints) {}

    // ------------------------------------------------------------------
    // Points
    // ------------------------------------------------------------------

    /**
     * Add a point. For a QR code, (x, y) is where the code hangs on the wall
     * and qr_yaw the direction its face points; give qr_z (the code's height
     * in the map frame) or qr_height (above the floor, default 1.4 m).
     *
     * @param  array{kind: string, name: string, floor_code: string, x: float, y: float, unit_code?: string|null, is_primary?: bool, facility_type?: string|null, checkpoint_code?: string|null, qr_z?: float|null, qr_height?: float|null, qr_yaw?: float|null}  $data
     *
     * @throws InvalidArgumentException when the point cannot be added
     */
    public function createPoint(MallMap $map, array $data): MapEndPoint
    {
        $kind = $data['kind'];
        if (! array_key_exists($kind, MapEndPoint::KINDS)) {
            throw new InvalidArgumentException('Choose what the point is: '.implode(', ', array_keys(MapEndPoint::KINDS)).'.');
        }
        $elevation = $this->elevation($map, $data['floor_code']);
        if ($elevation === null) {
            throw new InvalidArgumentException("This map has no floor {$data['floor_code']}.");
        }
        $name = trim($data['name']);

        $fields = ['unit_code' => null, 'is_primary' => false, 'facility_type' => null, 'checkpoint_code' => null, 'qr' => null];
        if ($kind === 'shop_entrance') {
            $unit = trim((string) ($data['unit_code'] ?? ''));
            if ($unit === '' || strlen($unit) > 40) {
                throw new InvalidArgumentException('Give the shop unit code, for example G-05.');
            }
            if (! empty($data['is_primary']) && $this->unitHasPrimary($map, $unit)) {
                throw new InvalidArgumentException("Unit {$unit} already has a main door on this map. Untick “Main door”, or delete the other one first.");
            }
            $fields['unit_code'] = $unit;
            $fields['is_primary'] = ! empty($data['is_primary']);
        } elseif ($kind === 'facility') {
            $type = trim((string) ($data['facility_type'] ?? ''));
            if (! preg_match('/^[a-z][a-z_]{0,39}$/', $type)) {
                throw new InvalidArgumentException('Give the facility type, for example toilet or prayer_room.');
            }
            if ($this->facilityExists($map, $name)) {
                throw new InvalidArgumentException("A facility called “{$name}” is already on this map. Give this one another name.");
            }
            $fields['facility_type'] = $type;
        } elseif ($kind === 'checkpoint') {
            $code = trim((string) ($data['checkpoint_code'] ?? ''));
            if (strlen($code) > 60 || ! preg_match(self::CODE_PATTERN, $code)) {
                throw new InvalidArgumentException('Give the QR code a code such as G-C10 (letters, digits, dashes).');
            }
            if ($this->checkpointExists($map, $code)) {
                throw new InvalidArgumentException("QR code {$code} is already on this map.");
            }
            $height = isset($data['qr_z']) ? (float) $data['qr_z'] - $elevation : (float) ($data['qr_height'] ?? 1.4);
            if ($height < 0.2 || $height > 3.0) {
                throw new InvalidArgumentException('A QR code hangs between 0.2 and 3 m above the floor.');
            }
            $fields['checkpoint_code'] = $code;
            $fields['qr'] = [
                'x_m' => round((float) $data['x'], 3),
                'y_m' => round((float) $data['y'], 3),
                'z_m' => round($elevation + $height, 3),
                'yaw_deg' => round(fmod(fmod((float) ($data['qr_yaw'] ?? 0), 360.0) + 360.0, 360.0), 1),
                'marker_height_m' => round($height, 2),
                'physical_width_m' => 0.18,
            ];
        }

        return DB::transaction(function () use ($map, $kind, $name, $data, $elevation, $fields): MapEndPoint {
            $point = MapEndPoint::query()->create($fields + [
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'kind' => $kind,
                'sequence' => $this->nextSequence($map),
                'name' => $name,
                'floor_code' => $data['floor_code'],
                'x_m' => round((float) $data['x'], 3),
                'y_m' => round((float) $data['y'], 3),
                'z_m' => $elevation,
            ]);
            $this->applyPoint($point, $map, $this->settings($map));
            $this->syncCheckpoints($map);

            return $point->refresh();
        });
    }

    /**
     * Rename a point; for a facility the name is the facility's name, for a QR
     * code its label.
     *
     * @throws InvalidArgumentException when a facility of that name exists
     */
    public function renamePoint(MapEndPoint $point, string $name): void
    {
        $name = trim($name);
        $map = $point->mallMap;
        if ($point->kind === 'facility' && $name !== $point->name && $this->facilityExists($map, $name)) {
            throw new InvalidArgumentException("A facility called “{$name}” is already on this map.");
        }
        $point->forceFill(['name' => $name])->save();
        if ($point->kind !== 'node') {
            $this->applyAll($map);
        }
    }

    public function deletePoint(MapEndPoint $point): void
    {
        $map = $point->mallMap;
        DB::transaction(function () use ($point, $map): void {
            $point->delete();
            $this->applyAll($map);
        });
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
        if ($this->elevation($map, $floorCode) === null) {
            throw new InvalidArgumentException("This map has no floor {$floorCode}.");
        }
        $corners = array_values(array_map(fn ($corner): array => [round((float) $corner[0], 3), round((float) $corner[1], 3)], $corners));
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

        return DB::transaction(function () use ($map, $floorCode, $corners, $stepFree, $oneWay, $name): MapPath {
            $path = MapPath::query()->create([
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'sequence' => $this->nextSequence($map),
                'name' => $name === null || trim($name) === '' ? null : trim($name),
                'floor_code' => $floorCode,
                'points' => $corners,
                'is_accessible' => $stepFree,
                'one_way' => $oneWay,
            ]);
            $this->applyPath($path, $map, $this->settings($map));
            if ($path->refresh()->problem !== null) {
                throw new InvalidArgumentException($path->problem);
            }

            return $path;
        });
    }

    public function renamePath(MapPath $path, ?string $name): void
    {
        $path->forceFill(['name' => $name === null || trim($name) === '' ? null : trim($name)])->save();
    }

    public function deletePath(MapPath $path): void
    {
        $map = $path->mallMap;
        DB::transaction(function () use ($path, $map): void {
            $path->delete();
            $this->applyAll($map);
        });
    }

    // ------------------------------------------------------------------
    // Applying edits
    // ------------------------------------------------------------------

    /**
     * Remove everything the edits added and apply them again in the order
     * they were made, keeping the closures of the edges they add.
     */
    public function applyAll(MallMap $map): void
    {
        DB::transaction(function () use ($map): void {
            $closed = $this->closedEditEdges($map);
            $this->clear($map);
            $settings = $this->settings($map);

            $edits = collect()
                ->concat(MapEndPoint::query()->where('mall_map_id', $map->id)->get())
                ->concat(MapPath::query()->where('mall_map_id', $map->id)->get())
                ->sortBy(fn (MapEndPoint|MapPath $edit): string => sprintf('%012d-%d-%012d', $edit->sequence, $edit instanceof MapPath ? 1 : 0, $edit->id));
            foreach ($edits as $edit) {
                $edit instanceof MapPath ? $this->applyPath($edit, $map, $settings) : $this->applyPoint($edit, $map, $settings);
            }

            $this->restoreClosures($map, $closed);
            $this->syncCheckpoints($map);
        });
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
     * Remove what the edits added (the package's graph stays).
     */
    public function clear(MallMap $map): void
    {
        foreach (['map_shop_entrances', 'map_facility_anchors', 'map_checkpoint_anchors'] as $table) {
            DB::table($table)->where('mall_map_id', $map->id)->whereNotNull('map_end_point_id')->delete();
        }
        $owned = fn ($query) => $query->whereNotNull('map_end_point_id')->orWhereNotNull('map_path_id');
        DB::table('map_edges')->where('mall_map_id', $map->id)->where($owned)->delete();
        DB::table('map_nodes')->where('mall_map_id', $map->id)->where($owned)->delete();
        MapEndPoint::query()->where('mall_map_id', $map->id)->update(['map_node_id' => null, 'link_m' => null]);
    }

    /**
     * @param  array{walk: float, standoff: float, link_max: float}  $settings
     */
    private function applyPoint(MapEndPoint $point, MallMap $map, array $settings): void
    {
        $elevation = $this->elevation($map, $point->floor_code);
        $problem = match (true) {
            $elevation === null => "This map has no floor {$point->floor_code}.",
            $point->kind === 'facility' && $this->facilityExists($map, $point->name) => "A facility called “{$point->name}” is already on this map.",
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
                'is_primary' => $point->is_primary && ! $this->unitHasPrimary($map, (string) $point->unit_code),
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
    private function closedEditEdges(MallMap $map): array
    {
        return DB::table('map_edges as e')
            ->join('map_nodes as a', 'a.id', '=', 'e.from_node_id')
            ->join('map_nodes as b', 'b.id', '=', 'e.to_node_id')
            ->where('e.mall_map_id', $map->id)->where('e.status', 'closed')
            ->where(fn ($q) => $q->whereNotNull('e.map_end_point_id')->orWhereNotNull('e.map_path_id'))
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

    private function unitHasPrimary(MallMap $map, string $unitCode): bool
    {
        return DB::table('map_shop_entrances')->where('mall_map_id', $map->id)->where('unit_code', $unitCode)->where('is_primary', true)->exists();
    }

    private function facilityExists(MallMap $map, string $name): bool
    {
        return DB::table('map_facility_anchors')->where('mall_map_id', $map->id)->where('facility_name', $name)->exists();
    }

    private function checkpointExists(MallMap $map, string $code, ?int $exceptPoint = null): bool
    {
        return DB::table('map_checkpoint_anchors')->where('mall_map_id', $map->id)->where('checkpoint_code', $code)->exists()
            || MapEndPoint::query()->where('mall_map_id', $map->id)->where('checkpoint_code', $code)
                ->when($exceptPoint !== null, fn ($query) => $query->whereKeyNot($exceptPoint))
                ->whereNotNull('map_node_id')->exists();
    }

    private function nextSequence(MallMap $map): int
    {
        return 1 + max(
            (int) MapEndPoint::query()->where('mall_map_id', $map->id)->max('sequence'),
            (int) MapPath::query()->where('mall_map_id', $map->id)->max('sequence'),
        );
    }

    /**
     * A key no point or path of any map has: 10 lower-case letters and digits.
     */
    private function newKey(): string
    {
        do {
            $key = Str::lower(Str::random(10));
        } while (MapEndPoint::query()->where('key', $key)->exists() || MapPath::query()->where('key', $key)->exists());

        return $key;
    }
}
