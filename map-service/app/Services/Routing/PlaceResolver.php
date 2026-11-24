<?php

namespace App\Services\Routing;

use App\Exceptions\RouteNotPossible;
use App\Models\Checkpoint;
use App\Models\MallMap;
use Illuminate\Support\Facades\DB;

/**
 * Turns a place written as "kind:value" into the graph nodes it stands for:
 *
 * - checkpoint:<public slug>   where the visitor stands at a scanned QR code
 * - node:<node code>           one graph node
 * - unit:<shop unit code>      every entrance of the shop (the nearest is used)
 * - facility:<facility name>   one facility
 * - facility-type:<type>       every facility of that type (the nearest is used)
 * - point:<key>                an end point an administrator added (ISM-114)
 */
class PlaceResolver
{
    public const KINDS = ['checkpoint', 'node', 'unit', 'facility', 'facility-type', 'point'];

    /**
     * @return array{place: string, kind: string, value: string, label: string, node_ids: list<int>}
     *
     * @throws RouteNotPossible
     */
    public function resolve(MallMap $map, RouteGraph $graph, string $place, string $field): array
    {
        [$kind, $value] = array_pad(explode(':', $place, 2), 2, '');
        if (! in_array($kind, self::KINDS, true) || $value === '') {
            throw new RouteNotPossible('unknown_place', "Write {$field} as kind:value, with kind one of ".implode(', ', self::KINDS).'.', $field);
        }

        [$label, $nodeIds] = match ($kind) {
            'checkpoint' => $this->checkpoint($map, $value),
            'node' => $this->node($graph, $value),
            'unit' => ["Shop {$value}", $this->ids(DB::table('map_shop_entrances')->where('mall_map_id', $map->id)->where('unit_code', $value)->orderByDesc('is_primary')->pluck('map_node_id'))],
            'facility' => [$value, $this->ids(DB::table('map_facility_anchors')->where('mall_map_id', $map->id)->where('facility_name', $value)->pluck('map_node_id'))],
            'facility-type' => ['the nearest '.str_replace('_', ' ', $value), $this->ids(DB::table('map_facility_anchors')->where('mall_map_id', $map->id)->where('facility_type', $value)->pluck('map_node_id'))],
            'point' => $this->endPoint($map, $value),
        };

        if ($nodeIds === []) {
            throw new RouteNotPossible('unknown_place', "{$place} is not on this map.", $field);
        }

        return ['place' => $place, 'kind' => $kind, 'value' => $value, 'label' => $label, 'node_ids' => $nodeIds];
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function checkpoint(MallMap $map, string $slug): array
    {
        $checkpoint = $map->mall_ref === null ? null : Checkpoint::query()
            ->where('public_slug', $slug)->where('mall_ref', $map->mall_ref)->where('status', 'active')->first();
        if ($checkpoint === null) {
            return ['', []];
        }
        $nodeId = DB::table('map_checkpoint_anchors')->where('mall_map_id', $map->id)->where('checkpoint_code', $checkpoint->checkpoint_code)->value('map_node_id');

        return [$checkpoint->label ?? "QR code {$checkpoint->checkpoint_code}", $nodeId === null ? [] : [(int) $nodeId]];
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function endPoint(MallMap $map, string $key): array
    {
        $row = DB::table('map_end_points')->where('mall_map_id', $map->id)->where('key', $key)->whereNotNull('map_node_id')->first();

        return $row === null ? ['', []] : [$row->name, [(int) $row->map_node_id]];
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function node(RouteGraph $graph, string $code): array
    {
        $id = $graph->nodeIdOf($code);

        return [$code, $id === null ? [] : [$id]];
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return list<int>
     */
    private function ids(iterable $ids): array
    {
        $list = [];
        foreach ($ids as $id) {
            $list[] = (int) $id;
        }

        return array_values(array_unique($list));
    }
}
