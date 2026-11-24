<?php

namespace App\Services\Routing;

use App\Exceptions\RouteNotPossible;
use App\Models\MallMap;

/**
 * Plans a route on one map (ISM-62): from a start place through one or more
 * stops in order, over open edges only, optionally step-free (no stairs or
 * escalators), by time or by distance, with distance and time estimates and
 * spoken-style steps. Nothing is stored; a new QR scan or a closure simply
 * means asking again.
 */
class RoutePlanner
{
    private const TURN_DEGREES = 30.0;

    public function __construct(private PlaceResolver $places) {}

    /**
     * @param  list<string>  $stops  one or more destinations, visited in order
     * @param  'time'|'distance'  $cost
     * @return array<string, mixed>
     *
     * @throws RouteNotPossible
     */
    public function plan(MallMap $map, string $from, array $stops, bool $stepFree = false, string $cost = 'time'): array
    {
        $graph = RouteGraph::load($map);
        $start = $this->places->resolve($map, $graph, $from, 'from');
        $targets = [];
        foreach (array_values($stops) as $index => $stop) {
            $targets[] = $this->places->resolve($map, $graph, $stop, count($stops) === 1 ? 'to' : "to.{$index}");
        }

        $nodes = [];
        $edges = [];
        $legs = [];
        $sources = $start['node_ids'];
        foreach ($targets as $index => $target) {
            $path = $graph->cheapestPath($sources, $target['node_ids'], $cost, $stepFree);
            if ($path === null) {
                throw new RouteNotPossible('unreachable', "There is no open {$this->kind($stepFree)}route from {$start['label']} to {$target['label']}.", count($targets) === 1 ? 'to' : "to.{$index}");
            }
            $offset = count($nodes);
            if ($nodes !== []) {
                array_shift($path['nodes']);
            }
            array_push($nodes, ...$path['nodes']);
            array_push($edges, ...$path['edges']);
            $legs[] = [
                'to' => $target['place'],
                'label' => $target['label'],
                'node_code' => $graph->nodes[end($path['nodes']) ?: $sources[0]]['code'],
                'length_m' => round(array_sum(array_column($path['edges'], 'length')), 1),
                'seconds' => round(array_sum(array_column($path['edges'], 'seconds')), 1),
                'from_index' => max(0, $offset - 1),
                'to_index' => count($nodes) - 1,
            ];
            $sources = [$nodes[count($nodes) - 1]];
        }

        $length = array_sum(array_column($edges, 'length'));
        $seconds = array_sum(array_column($edges, 'seconds'));

        return [
            'map_id' => $map->id,
            'mall_ref' => $map->mall_ref,
            'cost' => $cost,
            'step_free' => $stepFree,
            'from' => ['place' => $start['place'], 'label' => $start['label'], 'node_code' => $graph->nodes[$nodes[0]]['code']],
            'to' => array_map(fn (array $leg): array => ['place' => $leg['to'], 'label' => $leg['label'], 'node_code' => $leg['node_code']], $legs),
            'total' => ['length_m' => round($length, 1), 'seconds' => round($seconds, 1), 'minutes' => max(1, (int) ceil($seconds / 60))],
            'floors' => array_values(array_unique(array_map(fn (int $id): string => $graph->nodes[$id]['floor'], $nodes))),
            'path' => array_map(fn (int $id): array => $this->point($graph->nodes[$id]), $nodes),
            'legs' => $legs,
            'steps' => $this->steps($graph, $nodes, $edges, $start['label'], $legs),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    private function kind(bool $stepFree): string
    {
        return $stepFree ? 'step-free ' : '';
    }

    /**
     * @param  array{code: string, floor: string, type: string, x: float, y: float, z: float}  $node
     * @return array<string, mixed>
     */
    private function point(array $node): array
    {
        return ['node_code' => $node['code'], 'floor_code' => $node['floor'], 'node_type' => $node['type'], 'position' => ['x' => $node['x'], 'y' => $node['y'], 'z' => $node['z']]];
    }

    /**
     * Walking stretches are joined until the direction changes by 30 degrees
     * or more; each lift, escalator or staircase is one step; each stop ends
     * with an arrival step.
     *
     * @param  list<int>  $nodes
     * @param  list<array{to: int, length: float, seconds: float, accessible: bool, open: bool, connector: int|null}>  $edges
     * @param  list<array<string, mixed>>  $legs
     * @return list<array<string, mixed>>
     */
    private function steps(RouteGraph $graph, array $nodes, array $edges, string $startLabel, array $legs): array
    {
        $first = $graph->nodes[$nodes[0]];
        $steps = [['type' => 'start', 'instruction' => "Start at {$startLabel}.", 'floor_code' => $first['floor'], 'node_code' => $first['code'], 'length_m' => 0.0, 'seconds' => 0.0]];
        $legEnds = array_column($legs, null, 'to_index');

        $walk = null;
        $heading = null;
        $flush = function () use (&$walk, &$steps): void {
            if ($walk !== null) {
                $metres = (int) max(1, round($walk['length_m']));
                $walk['instruction'] = ($walk['turn'] === null ? 'Walk' : $walk['turn'].' and walk')." {$metres} m.";
                $walk['length_m'] = round($walk['length_m'], 1);
                $walk['seconds'] = round($walk['seconds'], 1);
                unset($walk['turn']);
                $steps[] = $walk;
                $walk = null;
            }
        };

        foreach ($edges as $index => $edge) {
            $from = $graph->nodes[$nodes[$index]];
            $to = $graph->nodes[$nodes[$index + 1]];

            if ($edge['connector'] !== null) {
                $flush();
                $heading = null;
                $connector = $graph->connectors[$edge['connector']];
                $direction = $to['z'] > $from['z'] ? 'up' : 'down';
                $floorName = $graph->floorNames[$to['floor']] ?? "floor {$to['floor']}";
                $steps[] = ['type' => 'connector', 'instruction' => "Take {$connector['name']} {$direction} to {$floorName}.", 'floor_code' => $from['floor'], 'node_code' => $from['code'], 'to_floor_code' => $to['floor'], 'connector' => $connector['name'], 'connector_type' => $connector['type'], 'length_m' => round($edge['length'], 1), 'seconds' => round($edge['seconds'], 1)];
            } else {
                $dx = $to['x'] - $from['x'];
                $dy = $to['y'] - $from['y'];
                $turn = null;
                if (hypot($dx, $dy) >= 0.01) {
                    $newHeading = rad2deg(atan2($dy, $dx));
                    if ($heading !== null) {
                        $change = fmod($newHeading - $heading + 540.0, 360.0) - 180.0;
                        if (abs($change) >= self::TURN_DEGREES) {
                            $turn = $this->turnWords($change);
                        }
                    }
                    $heading = $newHeading;
                }
                if ($walk === null || $turn !== null) {
                    $flush();
                    $walk = ['type' => 'walk', 'instruction' => '', 'floor_code' => $from['floor'], 'node_code' => $from['code'], 'turn' => $turn, 'length_m' => 0.0, 'seconds' => 0.0];
                }
                $walk['length_m'] += $edge['length'];
                $walk['seconds'] += $edge['seconds'];
            }

            if (isset($legEnds[$index + 1])) {
                $flush();
                $leg = $legEnds[$index + 1];
                $last = $index + 1 === count($edges);
                $steps[] = ['type' => $last ? 'arrive' : 'stop', 'instruction' => ($last ? 'Arrive at ' : 'Stop at ')."{$leg['label']}.", 'floor_code' => $to['floor'], 'node_code' => $to['code'], 'length_m' => 0.0, 'seconds' => 0.0];
            }
        }
        $flush();

        if (end($steps)['type'] === 'start') {
            $steps[] = ['type' => 'arrive', 'instruction' => 'You are already there.', 'floor_code' => $first['floor'], 'node_code' => $first['code'], 'length_m' => 0.0, 'seconds' => 0.0];
        }

        return array_values($steps);
    }

    /**
     * Headings turn counterclockwise (x east, y north), so a positive change is a left turn.
     */
    private function turnWords(float $change): string
    {
        $side = $change > 0 ? 'left' : 'right';

        return match (true) {
            abs($change) > 150 => 'Turn around',
            abs($change) < 60 => "Bear {$side}",
            default => "Turn {$side}",
        };
    }
}
