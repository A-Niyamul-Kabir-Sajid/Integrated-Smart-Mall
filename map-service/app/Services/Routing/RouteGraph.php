<?php

namespace App\Services\Routing;

use App\Models\MallMap;
use Illuminate\Support\Facades\DB;

/**
 * One map's navigation graph in memory, read fresh for each request so edge
 * closures take effect at once.
 */
class RouteGraph
{
    /** @var array<int, int>|null node id => position in node-code order */
    private ?array $ranks = null;

    /**
     * @param  array<int, array{id: int, code: string, floor: string, type: string, x: float, y: float, z: float, accessible: bool}>  $nodes
     * @param  array<int, list<array{to: int, length: float, seconds: float, accessible: bool, open: bool, connector: int|null}>>  $adjacency
     * @param  array<int, array{name: string, type: string}>  $connectors
     * @param  array<string, string>  $floorNames
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $adjacency,
        public readonly array $connectors,
        public readonly array $floorNames,
    ) {}

    public static function load(MallMap $map): self
    {
        $nodes = [];
        foreach (DB::table('map_nodes')->where('mall_map_id', $map->id)->orderBy('node_code')->get() as $row) {
            $nodes[(int) $row->id] = [
                'id' => (int) $row->id,
                'code' => $row->node_code,
                'floor' => $row->floor_code,
                'type' => $row->node_type,
                'x' => (float) $row->x_m,
                'y' => (float) $row->y_m,
                'z' => (float) $row->z_m,
                'accessible' => (bool) $row->is_accessible,
            ];
        }

        $adjacency = [];
        $rows = DB::table('map_edges')->where('mall_map_id', $map->id)->orderBy('id')->get();
        $statusById = $rows->pluck('status', 'id');
        foreach ($rows as $row) {
            // An edge an end point added next to a walkway is closed whenever that walkway is.
            $parentOpen = $row->parent_edge_id === null || ($statusById[$row->parent_edge_id] ?? 'open') === 'open';
            $adjacency[(int) $row->from_node_id][] = [
                'to' => (int) $row->to_node_id,
                'length' => (float) $row->length_m,
                'seconds' => (float) $row->expected_seconds,
                'accessible' => (bool) $row->is_accessible,
                'open' => $row->status === 'open' && $parentOpen,
                'connector' => $row->map_connector_id === null ? null : (int) $row->map_connector_id,
            ];
        }

        $connectors = DB::table('map_connectors')->where('mall_map_id', $map->id)->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => ['name' => $row->name, 'type' => $row->connector_type]])->all();

        $floorNames = DB::table('map_floors')->where('mall_map_id', $map->id)->pluck('name', 'floor_code')->all();

        return new self($nodes, $adjacency, $connectors, $floorNames);
    }

    public function nodeIdOf(string $code): ?int
    {
        foreach ($this->nodes as $id => $node) {
            if ($node['code'] === $code) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Cheapest path from any of $sources to any of $targets, or null.
     * Dijkstra with a binary heap; equal costs are settled in node-code
     * order, so the same request always gives the same path.
     *
     * @param  list<int>  $sources
     * @param  list<int>  $targets
     * @param  'time'|'distance'  $cost
     * @return array{nodes: list<int>, edges: list<array{to: int, length: float, seconds: float, accessible: bool, open: bool, connector: int|null}>}|null
     */
    public function cheapestPath(array $sources, array $targets, string $cost, bool $stepFree): ?array
    {
        $targetSet = array_fill_keys($targets, true);
        $best = [];
        $previous = [];
        $heap = new \SplPriorityQueue;
        $heap->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        $sequence = PHP_INT_MAX;
        $push = function (int $node, float $distance) use ($heap, &$sequence): void {
            // SplPriorityQueue is a max-heap: negate the cost and break ties by node code.
            $heap->insert([$node, $distance], [-round($distance, 6), $this->tieBreak($node), $sequence--]);
        };

        foreach ($sources as $source) {
            if (isset($this->nodes[$source])) {
                $best[$source] = 0.0;
                $push($source, 0.0);
            }
        }

        $settled = [];
        while (! $heap->isEmpty()) {
            [$node, $distance] = $heap->extract();
            if (isset($settled[$node]) || $distance > $best[$node] + 1e-9) {
                continue;
            }
            $settled[$node] = true;

            if (isset($targetSet[$node])) {
                return $this->assemble($node, $previous);
            }

            foreach ($this->adjacency[$node] ?? [] as $edge) {
                $next = $edge['to'];
                if (! $edge['open'] || isset($settled[$next])) {
                    continue;
                }
                if ($stepFree && (! $edge['accessible'] || (! $this->nodes[$next]['accessible'] && ! isset($targetSet[$next])))) {
                    continue;
                }
                $candidate = $distance + ($cost === 'distance' ? $edge['length'] : $edge['seconds']);
                if (! isset($best[$next]) || $candidate < $best[$next] - 1e-9) {
                    $best[$next] = $candidate;
                    $previous[$next] = [$node, $edge];
                    $push($next, $candidate);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0: int, 1: array{to: int, length: float, seconds: float, accessible: bool, open: bool, connector: int|null}}>  $previous
     * @return array{nodes: list<int>, edges: list<array{to: int, length: float, seconds: float, accessible: bool, open: bool, connector: int|null}>}
     */
    private function assemble(int $end, array $previous): array
    {
        $nodes = [$end];
        $edges = [];
        while (isset($previous[$nodes[0]])) {
            [$from, $edge] = $previous[$nodes[0]];
            array_unshift($nodes, $from);
            array_unshift($edges, $edge);
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * A larger number for codes that sort earlier, so the max-heap pops them first.
     */
    private function tieBreak(int $node): int
    {
        if ($this->ranks === null) {
            $codes = array_map(fn (array $n): string => $n['code'], $this->nodes);
            asort($codes, SORT_STRING);
            $this->ranks = array_flip(array_keys($codes));
        }

        return count($this->nodes) - $this->ranks[$node];
    }
}
