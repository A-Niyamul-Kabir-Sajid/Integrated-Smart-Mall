<?php

namespace App\Services;

use App\Models\MallMap;
use App\Models\MapEndPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * End points (ISM-114): destinations an administrator adds to a map. Each one
 * gets a short random key, becomes a node of the navigation graph joined to
 * the nearest walkway of its floor, and can be routed to as "point:<key>".
 *
 * Joining adds a junction on the walkway (two half edges per direction, whose
 * parent is the walkway, so closing the walkway closes them too) and an edge
 * each way between the junction and the end point. Everything added carries
 * the end point's id and is removed with it; the map's own graph is never
 * changed.
 */
class EndPointService
{
    /** A junction closer than this to a walkway's end uses that end node instead. */
    private const SNAP_M = 0.25;

    /**
     * Add an end point at (x, y) on a floor and join it to the graph.
     *
     * @throws InvalidArgumentException when the floor is unknown or no walkway is near enough
     */
    public function create(MallMap $map, string $name, string $floorCode, float $x, float $y): MapEndPoint
    {
        $elevation = DB::table('map_floors')->where('mall_map_id', $map->id)->where('floor_code', $floorCode)->value('elevation_m');
        if ($elevation === null) {
            throw new InvalidArgumentException("This map has no floor {$floorCode}.");
        }

        return DB::transaction(function () use ($map, $name, $floorCode, $x, $y, $elevation): MapEndPoint {
            $endPoint = MapEndPoint::query()->create([
                'mall_map_id' => $map->id,
                'key' => $this->newKey(),
                'name' => $name,
                'floor_code' => $floorCode,
                'x_m' => round($x, 3),
                'y_m' => round($y, 3),
                'z_m' => (float) $elevation,
            ]);
            $this->link($endPoint, $map);

            return $endPoint->refresh();
        });
    }

    public function rename(MapEndPoint $endPoint, string $name): void
    {
        $endPoint->forceFill(['name' => $name])->save();
    }

    public function delete(MapEndPoint $endPoint): void
    {
        DB::transaction(function () use ($endPoint): void {
            $this->unlink($endPoint);
            $endPoint->delete();
        });
    }

    /**
     * Join every end point of a map to its (new) graph again, for example
     * after map:rebuild-graph. End points that cannot be joined stay unlinked.
     *
     * @return int how many are linked
     */
    public function relinkAll(MallMap $map): int
    {
        $linked = 0;
        foreach (MapEndPoint::query()->where('mall_map_id', $map->id)->orderBy('id')->get() as $endPoint) {
            $this->unlink($endPoint);
            try {
                $this->link($endPoint, $map);
                $linked++;
            } catch (InvalidArgumentException) {
                // Stays unlinked; the admin page shows it as "not on the map".
            }
        }

        return $linked;
    }

    /**
     * Copy the end points of another map of the same mall into a new map
     * version, with the same keys, so links that apps already use keep working.
     */
    public function copyInto(MallMap $from, MallMap $to): int
    {
        $copied = 0;
        foreach (MapEndPoint::query()->where('mall_map_id', $from->id)->orderBy('id')->get() as $source) {
            $elevation = DB::table('map_floors')->where('mall_map_id', $to->id)->where('floor_code', $source->floor_code)->value('elevation_m');
            $copy = MapEndPoint::query()->create([
                'mall_map_id' => $to->id,
                'key' => $source->key,
                'name' => $source->name,
                'floor_code' => $source->floor_code,
                'x_m' => $source->x_m,
                'y_m' => $source->y_m,
                'z_m' => $elevation === null ? $source->z_m : (float) $elevation,
            ]);
            try {
                $this->link($copy, $to);
            } catch (InvalidArgumentException) {
                // Kept unlinked: the new version has no walkway near it.
            }
            $copied++;
        }

        return $copied;
    }

    /**
     * Join an end point to the nearest walkway of its floor.
     *
     * @throws InvalidArgumentException when no walkway is near enough
     */
    public function link(MapEndPoint $endPoint, MallMap $map): void
    {
        $nodes = DB::table('map_nodes')->where('mall_map_id', $map->id)->whereNull('map_end_point_id')
            ->where('floor_code', $endPoint->floor_code)->get()->keyBy('id');

        // Walkways of the floor that belong to the package (not to other end points), by node pair.
        $pairs = [];
        foreach (DB::table('map_edges')->where('mall_map_id', $map->id)->whereNull('map_end_point_id')->whereNull('map_connector_id')->get() as $edge) {
            if (! isset($nodes[$edge->from_node_id], $nodes[$edge->to_node_id])) {
                continue;
            }
            $key = min($edge->from_node_id, $edge->to_node_id).'-'.max($edge->from_node_id, $edge->to_node_id);
            $pairs[$key][] = $edge;
        }

        $best = null;
        foreach ($pairs as $edges) {
            $a = $nodes[min($edges[0]->from_node_id, $edges[0]->to_node_id)];
            $b = $nodes[max($edges[0]->from_node_id, $edges[0]->to_node_id)];
            [$qx, $qy, $t] = $this->project($endPoint->x_m, $endPoint->y_m, (float) $a->x_m, (float) $a->y_m, (float) $b->x_m, (float) $b->y_m);
            $distance = hypot($endPoint->x_m - $qx, $endPoint->y_m - $qy);
            if ($best === null || $distance < $best['distance'] - 1e-9) {
                $best = ['distance' => $distance, 'a' => $a, 'b' => $b, 'qx' => $qx, 'qy' => $qy, 't' => $t, 'edges' => $edges];
            }
        }

        $limit = (float) config('mapservice.end_point_link_max_m', 6.0);
        if ($best === null || $best['distance'] > $limit) {
            throw new InvalidArgumentException(sprintf('No walkway of floor %s is within %.0f m of this point. Place it closer to the navigation graph.', $endPoint->floor_code, $limit));
        }

        $walkSpeed = (float) ($map->package()['settings']['ism_walk_mps'] ?? 1.2);
        $now = ['mall_map_id' => $map->id, 'map_end_point_id' => $endPoint->id];

        $a = $best['a'];
        $b = $best['b'];
        $alongA = hypot($best['qx'] - $a->x_m, $best['qy'] - $a->y_m);
        $alongB = hypot($best['qx'] - $b->x_m, $best['qy'] - $b->y_m);
        if ($alongA <= self::SNAP_M) {
            $junctionId = (int) $a->id;
        } elseif ($alongB <= self::SNAP_M) {
            $junctionId = (int) $b->id;
        } else {
            $junctionId = DB::table('map_nodes')->insertGetId($now + [
                'floor_code' => $endPoint->floor_code,
                'node_code' => 'P-'.$endPoint->key.'-J',
                'node_type' => 'junction',
                'x_m' => round($best['qx'], 3),
                'y_m' => round($best['qy'], 3),
                'z_m' => $endPoint->z_m,
                'is_accessible' => true,
            ]);
            foreach ($best['edges'] as $parent) {
                $from = $nodes[$parent->from_node_id];
                $to = $nodes[$parent->to_node_id];
                $first = hypot($best['qx'] - $from->x_m, $best['qy'] - $from->y_m);
                $second = hypot($best['qx'] - $to->x_m, $best['qy'] - $to->y_m);
                foreach ([[$parent->from_node_id, $junctionId, $first], [$junctionId, $parent->to_node_id, $second]] as [$fromId, $toId, $straight]) {
                    $share = $straight / max($first + $second, 1e-6);
                    DB::table('map_edges')->insert($now + [
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
        }

        $nodeId = DB::table('map_nodes')->insertGetId($now + [
            'floor_code' => $endPoint->floor_code,
            'node_code' => 'P-'.$endPoint->key,
            'node_type' => 'endpoint',
            'x_m' => $endPoint->x_m,
            'y_m' => $endPoint->y_m,
            'z_m' => $endPoint->z_m,
            'is_accessible' => true,
        ]);
        $length = round(max(0.01, $best['distance']), 3);
        foreach ([[$junctionId, $nodeId], [$nodeId, $junctionId]] as [$fromId, $toId]) {
            DB::table('map_edges')->insert($now + [
                'from_node_id' => $fromId,
                'to_node_id' => $toId,
                'length_m' => $length,
                'expected_seconds' => round(max(0.01, $length / max($walkSpeed, 0.1)), 2),
                'is_accessible' => true,
                'status' => 'open',
                'parent_edge_id' => null,
            ]);
        }

        $endPoint->forceFill(['map_node_id' => $nodeId, 'link_m' => round($best['distance'], 2)])->save();
    }

    /**
     * Remove the nodes and edges an end point added.
     */
    public function unlink(MapEndPoint $endPoint): void
    {
        DB::table('map_edges')->where('map_end_point_id', $endPoint->id)->delete();
        DB::table('map_nodes')->where('map_end_point_id', $endPoint->id)->delete();
        if ($endPoint->map_node_id !== null || $endPoint->link_m !== null) {
            $endPoint->forceFill(['map_node_id' => null, 'link_m' => null])->save();
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float} the closest point of segment AB to P and its position 0..1
     */
    private function project(float $px, float $py, float $ax, float $ay, float $bx, float $by): array
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared < 1e-12 ? 0.0 : max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSquared));

        return [$ax + $t * $dx, $ay + $t * $dy, $t];
    }

    private function newKey(): string
    {
        do {
            $key = Str::lower(Str::random(10));
        } while (MapEndPoint::query()->where('key', $key)->exists());

        return $key;
    }
}
