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

    /** Walking pieces shorter than this are folded into a neighbouring walk step. */
    private const SHORT_WALK_METRES = 2.0;

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
                $saysDirection = preg_match('/\b'.$direction.'\b/i', $connector['name']) === 1;
                $steps[] = ['type' => 'connector', 'instruction' => "Take {$connector['name']}".($saysDirection ? '' : " {$direction}")." to {$floorName}.", 'floor_code' => $from['floor'], 'node_code' => $from['code'], 'to_floor_code' => $to['floor'], 'connector' => $connector['name'], 'connector_type' => $connector['type'], 'length_m' => round($edge['length'], 1), 'seconds' => round($edge['seconds'], 1)];
            } else {
                $dx = $to['x'] - $from['x'];
                $dy = $to['y'] - $from['y'];
                $turns = false;
                $before = $heading;
                $newHeading = null;
                if (hypot($dx, $dy) >= 0.01) {
                    $newHeading = rad2deg(atan2($dy, $dx));
                    $turns = $heading !== null && abs($this->headingChange($heading, $newHeading)) >= self::TURN_DEGREES;
                    $heading = $newHeading;
                }
                if ($walk === null || $turns) {
                    $flush();
                    $walk = ['type' => 'walk', 'instruction' => '', 'floor_code' => $from['floor'], 'node_code' => $from['code'], 'before' => $before, 'first' => $newHeading, 'length_m' => 0.0, 'seconds' => 0.0];
                }
                $walk['first'] ??= $newHeading;
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
        $steps = $this->foldShortWalks($steps);

        if (end($steps)['type'] === 'start') {
            $steps[] = ['type' => 'arrive', 'instruction' => 'You are already there.', 'floor_code' => $first['floor'], 'node_code' => $first['code'], 'length_m' => 0.0, 'seconds' => 0.0];
        }

        return array_values($steps);
    }

    /**
     * Fold walking pieces under 2 m (small jogs of the drawn graph) into the
     * next walk step on the same floor, or else the previous one, then write
     * each walk step's words.
     *
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function foldShortWalks(array $steps): array
    {
        $isWalk = fn (?array $step, string $floor): bool => $step !== null && $step['type'] === 'walk' && $step['floor_code'] === $floor;

        for ($i = 0; $i < count($steps); $i++) {
            $step = $steps[$i];
            if ($step['type'] !== 'walk' || $step['length_m'] >= self::SHORT_WALK_METRES) {
                continue;
            }
            $next = $steps[$i + 1] ?? null;
            $previous = $steps[$i - 1] ?? null;
            if ($isWalk($next, $step['floor_code'])) {
                // The turn of the merged step is measured from before the short piece.
                $steps[$i + 1]['length_m'] += $step['length_m'];
                $steps[$i + 1]['seconds'] += $step['seconds'];
                $steps[$i + 1]['node_code'] = $step['node_code'];
                $steps[$i + 1]['before'] = $step['before'];
            } elseif ($isWalk($previous, $step['floor_code'])) {
                $steps[$i - 1]['length_m'] += $step['length_m'];
                $steps[$i - 1]['seconds'] += $step['seconds'];
            } else {
                continue;
            }
            array_splice($steps, $i, 1);
            $i = max(-1, $i - 2);
        }

        // Work out each walk step's turn; a walk step without a turn joins the walk step before it.
        $joined = [];
        foreach ($steps as $step) {
            if ($step['type'] !== 'walk') {
                $joined[] = $step;

                continue;
            }
            $change = $step['before'] === null || $step['first'] === null ? 0.0 : $this->headingChange($step['before'], $step['first']);
            $step['turn'] = abs($change) >= self::TURN_DEGREES ? $this->turnWords($change) : null;
            $last = count($joined) - 1;
            if ($step['turn'] === null && $last >= 0 && $joined[$last]['type'] === 'walk' && $joined[$last]['floor_code'] === $step['floor_code']) {
                $joined[$last]['length_m'] += $step['length_m'];
                $joined[$last]['seconds'] += $step['seconds'];

                continue;
            }
            $joined[] = $step;
        }

        foreach ($joined as $index => $step) {
            if ($step['type'] === 'walk') {
                $metres = (int) max(1, round($step['length_m']));
                $joined[$index]['instruction'] = ($step['turn'] === null ? 'Walk' : $step['turn'].' and walk')." {$metres} m.";
                $joined[$index]['length_m'] = round($step['length_m'], 1);
                $joined[$index]['seconds'] = round($step['seconds'], 1);
                unset($joined[$index]['before'], $joined[$index]['first'], $joined[$index]['turn']);
            }
        }

        return $joined;
    }

    /**
     * The signed change from one heading to another, in (-180, 180].
     */
    private function headingChange(float $from, float $to): float
    {
        return fmod($to - $from + 540.0, 360.0) - 180.0;
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
