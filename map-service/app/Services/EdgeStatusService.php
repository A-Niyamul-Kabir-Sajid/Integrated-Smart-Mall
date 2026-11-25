<?php

namespace App\Services;

use App\Models\MallMap;
use App\Models\MapEdge;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Closes and reopens walkways of a map (an operational exception: cleaning,
 * repairs, a broken lift). Routes asked for afterwards avoid closed edges.
 */
class EdgeStatusService
{
    /**
     * @param  'open'|'closed'  $status
     * @return Collection<int, MapEdge> the edges that were changed
     *
     * @throws InvalidArgumentException when the nodes are not joined by an edge
     */
    public function set(MallMap $map, string $fromCode, string $toCode, string $status, ?string $reason = null, bool $bothWays = true): Collection
    {
        $ids = $map->nodes()->whereIn('node_code', [$fromCode, $toCode])->pluck('id', 'node_code');
        if (! isset($ids[$fromCode], $ids[$toCode])) {
            throw new InvalidArgumentException("{$fromCode} or {$toCode} is not a node of this map.");
        }

        $edges = $map->edges()->where(function ($query) use ($ids, $fromCode, $toCode, $bothWays): void {
            $query->where(fn ($q) => $q->where('from_node_id', $ids[$fromCode])->where('to_node_id', $ids[$toCode]));
            if ($bothWays) {
                $query->orWhere(fn ($q) => $q->where('from_node_id', $ids[$toCode])->where('to_node_id', $ids[$fromCode]));
            }
        })->get();
        if ($edges->isEmpty()) {
            throw new InvalidArgumentException("No walkway joins {$fromCode} and {$toCode}.");
        }

        foreach ($edges as $edge) {
            $edge->forceFill([
                'status' => $status,
                'closed_reason' => $status === 'closed' ? $reason : null,
                'status_changed_at' => now(),
            ])->save();
        }

        return $edges;
    }

    /**
     * Closed edges of a map with their node codes, newest first.
     *
     * @return Collection<int, MapEdge>
     */
    public function closed(MallMap $map): Collection
    {
        return $map->edges()->where('status', 'closed')->with(['fromNode', 'toNode', 'connector'])->latest('status_changed_at')->get();
    }
}
