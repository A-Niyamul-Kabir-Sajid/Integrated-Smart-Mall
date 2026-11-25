<?php

namespace App\Services;

use App\Models\MallMap;
use App\Models\MapConnector;
use App\Models\MapNode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Checks the graph part of a map package and writes it into the map_* tables
 * of one map (ISM-59). It writes only the package's own graph; MapEditService
 * rebuilds it and replays the administrators' edits on top (ISM-114). The Blender export validates too; this repeats the
 * structural rules on the server, because a package can be edited by hand.
 */
class GraphBuilder
{
    private const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.\-]*$/';

    /**
     * Every rule the package's graph breaks; empty when it can be stored.
     *
     * @param  array<string, mixed>  $package
     * @return list<string>
     */
    public function problems(array $package): array
    {
        $errors = [];

        $floors = [];
        foreach ($package['floors'] ?? [] as $index => $floor) {
            $code = $floor['floor_code'] ?? null;
            if (! is_string($code) || strlen($code) > 20 || ! preg_match(self::CODE_PATTERN, $code)) {
                $errors[] = "Floor {$index} has no valid floor_code.";

                continue;
            }
            if (isset($floors[$code])) {
                $errors[] = "Floor {$code} appears twice.";
            }
            if (! is_numeric($floor['elevation_m'] ?? null)) {
                $errors[] = "Floor {$code} has no elevation_m.";
            }
            $floors[$code] = true;
        }

        $nodes = [];
        foreach ($package['nodes'] ?? [] as $index => $node) {
            $code = $node['node_code'] ?? null;
            if (! is_string($code) || strlen($code) > 80 || ! preg_match(self::CODE_PATTERN, $code)) {
                $errors[] = "Node {$index} has no valid node_code.";

                continue;
            }
            if (isset($nodes[$code])) {
                $errors[] = "Node {$code} appears twice.";
            }
            if (! isset($floors[$node['floor_code'] ?? ''])) {
                $errors[] = "Node {$code} is on an unknown floor.";
            }
            if (! in_array($node['node_type'] ?? null, MapNode::TYPES, true)) {
                $errors[] = "Node {$code} has an unknown node_type.";
            }
            foreach (['x_m', 'y_m', 'z_m'] as $axis) {
                if (! is_numeric($node[$axis] ?? null)) {
                    $errors[] = "Node {$code} has no {$axis}.";
                }
            }
            $nodes[$code] = (string) ($node['floor_code'] ?? '');
        }

        $connectors = [];
        foreach ($package['connectors'] ?? [] as $index => $connector) {
            $name = $connector['name'] ?? null;
            if (! is_string($name) || $name === '' || strlen($name) > 80) {
                $errors[] = "Connector {$index} has no name.";

                continue;
            }
            if (isset($connectors[$name])) {
                $errors[] = "Connector {$name} appears twice.";
            }
            if (! in_array($connector['connector_type'] ?? null, MapConnector::TYPES, true)) {
                $errors[] = "Connector {$name} has an unknown connector_type.";
            }
            if (! in_array($connector['direction'] ?? null, ['both', 'up', 'down'], true)) {
                $errors[] = "Connector {$name} has an unknown direction.";
            } elseif (($connector['connector_type'] ?? null) === 'escalator' && $connector['direction'] === 'both') {
                $errors[] = "Escalator {$name} cannot run both ways.";
            }
            $stops = [];
            foreach ($connector['stops'] ?? [] as $stop) {
                $node = $stop['node_code'] ?? '';
                if (! isset($nodes[$node])) {
                    $errors[] = "Connector {$name} stops at unknown node {$node}.";
                } elseif ($nodes[$node] !== ($stop['floor_code'] ?? null)) {
                    $errors[] = "Connector {$name}: node {$node} is not on floor ".($stop['floor_code'] ?? '?').'.';
                }
                if (isset($stops[$stop['floor_code'] ?? ''])) {
                    $errors[] = "Connector {$name} stops twice on floor ".($stop['floor_code'] ?? '?').'.';
                }
                $stops[$stop['floor_code'] ?? ''] = $node;
            }
            if (count($stops) < 2) {
                $errors[] = "Connector {$name} needs stops on at least two floors.";
            }
            $connectors[$name] = array_values($stops);
        }

        $pairs = [];
        foreach ($package['edges'] ?? [] as $index => $edge) {
            $from = $edge['from_node_code'] ?? '';
            $to = $edge['to_node_code'] ?? '';
            $label = "Edge {$from} → {$to}";
            if (! isset($nodes[$from], $nodes[$to])) {
                $errors[] = "{$label} joins an unknown node.";

                continue;
            }
            if ($from === $to) {
                $errors[] = "{$label} starts and ends at the same node.";
            }
            if (isset($pairs[$from."\0".$to])) {
                $errors[] = "{$label} appears twice.";
            }
            $pairs[$from."\0".$to] = true;
            if (! is_numeric($edge['length_m'] ?? null) || $edge['length_m'] <= 0) {
                $errors[] = "{$label} needs a length_m above 0.";
            }
            if (! is_numeric($edge['expected_seconds'] ?? null) || $edge['expected_seconds'] <= 0) {
                $errors[] = "{$label} needs expected_seconds above 0.";
            }
            if (! in_array($edge['status'] ?? 'open', ['open', 'closed'], true)) {
                $errors[] = "{$label} has an unknown status.";
            }
            $connector = $edge['connector'] ?? null;
            if ($connector !== null && ! isset($connectors[$connector])) {
                $errors[] = "{$label} names unknown connector {$connector}.";
            }
            if ($nodes[$from] !== $nodes[$to]) {
                if ($connector === null) {
                    $errors[] = "{$label} changes floor without a connector.";
                } elseif (isset($connectors[$connector]) && (! in_array($from, $connectors[$connector], true) || ! in_array($to, $connectors[$connector], true))) {
                    $errors[] = "{$label} changes floor but its ends are not stops of {$connector}.";
                }
            }
        }

        $primaries = [];
        foreach ($package['shop_entrances'] ?? [] as $entrance) {
            $unit = $entrance['unit_code'] ?? null;
            $node = $entrance['node_code'] ?? '';
            if (! is_string($unit) || $unit === '' || strlen($unit) > 40) {
                $errors[] = "A shop entrance at {$node} has no unit_code.";

                continue;
            }
            if (! isset($nodes[$node])) {
                $errors[] = "Shop entrance of {$unit} is at unknown node {$node}.";
            } elseif ($nodes[$node] !== ($entrance['floor_code'] ?? null)) {
                $errors[] = "Shop entrance of {$unit}: node {$node} is not on floor ".($entrance['floor_code'] ?? '?').'.';
            }
            if (! empty($entrance['is_primary'])) {
                if (isset($primaries[$unit])) {
                    $errors[] = "Unit {$unit} has more than one primary entrance.";
                }
                $primaries[$unit] = true;
            }
        }

        $facilities = [];
        foreach ($package['facility_anchors'] ?? [] as $facility) {
            $name = $facility['facility_name'] ?? null;
            if (! is_string($name) || $name === '' || strlen($name) > 120) {
                $errors[] = 'A facility has no facility_name.';

                continue;
            }
            if (isset($facilities[$name])) {
                $errors[] = "Facility {$name} appears twice.";
            }
            $facilities[$name] = true;
            if (! isset($nodes[$facility['node_code'] ?? ''])) {
                $errors[] = "Facility {$name} is at an unknown node.";
            }
            if (! is_string($facility['facility_type'] ?? null) || $facility['facility_type'] === '') {
                $errors[] = "Facility {$name} has no facility_type.";
            }
        }

        $checkpoints = [];
        foreach ($package['checkpoints'] ?? [] as $checkpoint) {
            $code = $checkpoint['checkpoint_code'] ?? null;
            if (! is_string($code) || strlen($code) > 60 || ! preg_match(self::CODE_PATTERN, $code)) {
                $errors[] = 'A QR checkpoint has no valid checkpoint_code.';

                continue;
            }
            if (isset($checkpoints[$code])) {
                $errors[] = "QR checkpoint {$code} appears twice.";
            }
            $checkpoints[$code] = true;
            if (! isset($nodes[$checkpoint['node_code'] ?? ''])) {
                $errors[] = "QR checkpoint {$code} is at an unknown node.";
            }
            foreach (['x_m', 'y_m', 'z_m', 'yaw_deg'] as $field) {
                if (! is_numeric($checkpoint['qr'][$field] ?? null)) {
                    $errors[] = "QR checkpoint {$code} has no qr.{$field}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Write the package's graph into the tables of $map, replacing what was
     * there. Run inside the caller's transaction.
     *
     * @param  array<string, mixed>  $package
     *
     * @throws InvalidArgumentException when the graph breaks a rule
     */
    public function build(MallMap $map, array $package): void
    {
        $problems = $this->problems($package);
        if ($problems !== []) {
            $more = count($problems) > 5 ? ' (and '.(count($problems) - 5).' more)' : '';

            throw new InvalidArgumentException('The map graph is invalid: '.implode(' ', array_slice($problems, 0, 5)).$more);
        }

        DB::transaction(function () use ($map, $package): void {
            $this->clear($map);
            $mapId = $map->id;

            DB::table('map_floors')->insert(array_map(fn (array $floor): array => [
                'mall_map_id' => $mapId,
                'floor_code' => $floor['floor_code'],
                'name' => mb_substr((string) ($floor['name'] ?? $floor['floor_code']), 0, 120),
                'elevation_m' => (float) $floor['elevation_m'],
            ], $package['floors']));

            foreach (array_chunk($package['nodes'] ?? [], 300) as $chunk) {
                DB::table('map_nodes')->insert(array_map(fn (array $node): array => [
                    'mall_map_id' => $mapId,
                    'floor_code' => $node['floor_code'],
                    'node_code' => $node['node_code'],
                    'node_type' => $node['node_type'],
                    'x_m' => (float) $node['x_m'],
                    'y_m' => (float) $node['y_m'],
                    'z_m' => (float) $node['z_m'],
                    'is_accessible' => (bool) ($node['is_accessible'] ?? true),
                ], $chunk));
            }
            /** @var array<string, int> $nodeIds */
            $nodeIds = DB::table('map_nodes')->where('mall_map_id', $mapId)->pluck('id', 'node_code')->all();

            $connectorIds = [];
            foreach ($package['connectors'] ?? [] as $connector) {
                $connectorId = DB::table('map_connectors')->insertGetId([
                    'mall_map_id' => $mapId,
                    'name' => $connector['name'],
                    'connector_type' => $connector['connector_type'],
                    'direction' => $connector['direction'],
                    'is_accessible' => (bool) ($connector['is_accessible'] ?? true),
                ]);
                $connectorIds[$connector['name']] = $connectorId;
                foreach ($connector['stops'] as $stop) {
                    DB::table('map_connector_stops')->insert([
                        'mall_map_id' => $mapId,
                        'map_connector_id' => $connectorId,
                        'map_node_id' => $nodeIds[$stop['node_code']],
                        'floor_code' => $stop['floor_code'],
                    ]);
                }
            }

            foreach (array_chunk($package['edges'] ?? [], 300) as $chunk) {
                DB::table('map_edges')->insert(array_map(fn (array $edge): array => [
                    'mall_map_id' => $mapId,
                    'from_node_id' => $nodeIds[$edge['from_node_code']],
                    'to_node_id' => $nodeIds[$edge['to_node_code']],
                    'length_m' => (float) $edge['length_m'],
                    'expected_seconds' => (float) $edge['expected_seconds'],
                    'is_accessible' => (bool) ($edge['is_accessible'] ?? true),
                    'status' => $edge['status'] ?? 'open',
                    'map_connector_id' => isset($edge['connector']) ? $connectorIds[$edge['connector']] : null,
                    'closed_reason' => null,
                    'status_changed_at' => null,
                ], $chunk));
            }

            foreach ($package['shop_entrances'] ?? [] as $entrance) {
                DB::table('map_shop_entrances')->insert([
                    'mall_map_id' => $mapId,
                    'unit_code' => $entrance['unit_code'],
                    'map_node_id' => $nodeIds[$entrance['node_code']],
                    'is_primary' => (bool) ($entrance['is_primary'] ?? false),
                ]);
            }

            foreach ($package['facility_anchors'] ?? [] as $facility) {
                DB::table('map_facility_anchors')->insert([
                    'mall_map_id' => $mapId,
                    'facility_name' => $facility['facility_name'],
                    'facility_type' => $facility['facility_type'],
                    'map_node_id' => $nodeIds[$facility['node_code']],
                ]);
            }

            foreach ($package['checkpoints'] ?? [] as $checkpoint) {
                DB::table('map_checkpoint_anchors')->insert([
                    'mall_map_id' => $mapId,
                    'checkpoint_code' => $checkpoint['checkpoint_code'],
                    'label' => isset($checkpoint['label']) ? mb_substr((string) $checkpoint['label'], 0, 160) : null,
                    'map_node_id' => $nodeIds[$checkpoint['node_code']],
                    'qr' => json_encode($checkpoint['qr'], JSON_THROW_ON_ERROR),
                ]);
            }

        });
    }

    private function clear(MallMap $map): void
    {
        foreach (['map_checkpoint_anchors', 'map_facility_anchors', 'map_shop_entrances', 'map_edges', 'map_connector_stops', 'map_connectors', 'map_nodes', 'map_floors'] as $table) {
            DB::table($table)->where('mall_map_id', $map->id)->delete();
        }
    }
}
