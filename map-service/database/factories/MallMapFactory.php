<?php

namespace Database\Factories;

use App\Models\MallMap;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A map whose package (one floor G with a plan, preview and model) is written
 * to the local disk, so it works with Storage::fake('local') in tests.
 *
 * @extends Factory<MallMap>
 */
class MallMapFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' map',
            'status' => 'published',
            'format_version' => 1,
            'package_generated_at' => now(),
            'storage_dir' => 'maps/'.Str::uuid(),
            'stats' => ['floors' => 1, 'nodes' => 2, 'edges' => 2, 'checkpoints' => 0, 'shop_entrances' => 0, 'facility_anchors' => 0, 'connectors' => 0],
            'floor_codes' => ['G'],
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MallMap $map): void {
            $disk = Storage::disk('local');
            $disk->put($map->storage_dir.'/map_package.json', (string) json_encode(self::package()));
            $disk->put($map->storage_dir.'/floors/G_plan.png', 'png-bytes');
            $disk->put($map->storage_dir.'/floors/G_graph_preview.png', 'png-bytes');
            $disk->put($map->storage_dir.'/floors/G_model.glb', 'glb-bytes');
        });
    }

    /**
     * A minimal valid map package.
     *
     * @return array<string, mixed>
     */
    public static function package(): array
    {
        return [
            'format' => 'ism-map-package',
            'format_version' => 1,
            'generated_at' => '2026-11-22T09:29:52+00:00',
            'coordinate_frame' => 'ism-mall-local-v1: metres; right-handed; x and y horizontal, z up',
            'floors' => [[
                'floor_code' => 'G',
                'name' => 'Ground floor',
                'elevation_m' => 0.0,
                'plan' => ['file' => 'floors/G_plan.png', 'width_px' => 10, 'height_px' => 10, 'pixel_origin_x' => 0, 'pixel_origin_y' => 0, 'metres_per_pixel' => 0.05, 'rotation_deg' => 0, 'world_origin_x_m' => 0, 'world_origin_y_m' => 0.5],
                'graph_preview_file' => 'floors/G_graph_preview.png',
                'model' => ['file' => 'floors/G_model.glb', 'triangles' => 2, 'draco' => false],
            ]],
            'nodes' => [
                ['node_code' => 'G-N0001', 'floor_code' => 'G', 'node_type' => 'junction', 'x_m' => 0.0, 'y_m' => 0.0, 'z_m' => 0.0, 'is_accessible' => true],
                ['node_code' => 'G-N0002', 'floor_code' => 'G', 'node_type' => 'junction', 'x_m' => 3.0, 'y_m' => 0.0, 'z_m' => 0.0, 'is_accessible' => true],
            ],
            'edges' => [
                ['from_node_code' => 'G-N0001', 'to_node_code' => 'G-N0002', 'length_m' => 3.0, 'expected_seconds' => 2.5, 'is_accessible' => true, 'status' => 'open', 'connector' => null],
                ['from_node_code' => 'G-N0002', 'to_node_code' => 'G-N0001', 'length_m' => 3.0, 'expected_seconds' => 2.5, 'is_accessible' => true, 'status' => 'open', 'connector' => null],
            ],
            'connectors' => [],
            'checkpoints' => [],
            'shop_entrances' => [],
            'facility_anchors' => [],
            'stats' => ['floors' => 1, 'nodes' => 2, 'edges' => 2, 'checkpoints' => 0, 'shop_entrances' => 0, 'facility_anchors' => 0, 'connectors' => 0],
        ];
    }

    /**
     * A two-floor test mall with every kind of graph record, used by the
     * graph, checkpoint, route and embed tests. Walking costs length / 1.2 s.
     *
     *   Floor G (0 m): QR G-C01 at (0,0); junctions (10,0), (20,0), (10,9);
     *   shop G-101 primary door (20,3) and side door (12,9); Toilets G (10,11);
     *   lift stop (0,10); stairs stop (20,-5); escalator stop (15,-2).
     *   Floor 1 (5 m): QR 1-C01 at (5,10); junctions (10,10), (20,10);
     *   shop 1-205 door (20,8); Toilets 1 (20,12); Prayer room (0,12);
     *   lift stop (0,10); stairs stop (20,-5); escalator stop (15,8).
     *   Central Lift (both ways, step-free, 35 s), Main Stairs (both ways,
     *   12.5 s), East Escalator (up only, 10 s, not step-free).
     *
     * From G-C01 to 1-205: fastest and shortest by the escalator (29.0 s,
     * 29.8 m); step-free by the lift (61.7 s, 37 m); with the escalator closed
     * the fastest is the stairs (47.5 s, 50 m) and the shortest the lift.
     *
     * @return array<string, mixed>
     */
    public static function pilotPackage(): array
    {
        $nodes = [
            ['G-C01', 'G', 'checkpoint', 0, 0],
            ['G-N0001', 'G', 'junction', 10, 0],
            ['G-N0002', 'G', 'junction', 20, 0],
            ['G-N0003', 'G', 'junction', 10, 9],
            ['E-G-101', 'G', 'entrance', 20, 3],
            ['E-G-101-B', 'G', 'entrance', 12, 9],
            ['G-F-Toilets-G', 'G', 'facility', 10, 11],
            ['G-X-Central-Lift', 'G', 'connector', 0, 10],
            ['G-X-Main-Stairs', 'G', 'connector', 20, -5],
            ['G-X-East-Escalator', 'G', 'connector', 15, -2],
            ['1-C01', '1', 'checkpoint', 5, 10],
            ['1-N0001', '1', 'junction', 10, 10],
            ['1-N0002', '1', 'junction', 20, 10],
            ['E-1-205', '1', 'entrance', 20, 8],
            ['1-F-Toilets-1', '1', 'facility', 20, 12],
            ['1-F-Prayer-Room', '1', 'facility', 0, 12],
            ['1-X-Central-Lift', '1', 'connector', 0, 10],
            ['1-X-Main-Stairs', '1', 'connector', 20, -5],
            ['1-X-East-Escalator', '1', 'connector', 15, 8],
        ];
        $elevation = ['G' => 0.0, '1' => 5.0];
        $position = [];
        $nodeRows = [];
        foreach ($nodes as [$code, $floor, $type, $x, $y]) {
            $position[$code] = [$x, $y, $elevation[$floor]];
            $nodeRows[] = ['node_code' => $code, 'floor_code' => $floor, 'node_type' => $type, 'x_m' => (float) $x, 'y_m' => (float) $y, 'z_m' => $elevation[$floor], 'is_accessible' => true];
        }

        $edges = [];
        $walk = function (string $from, string $to) use (&$edges, $position): void {
            $length = round(hypot($position[$to][0] - $position[$from][0], $position[$to][1] - $position[$from][1]), 3);
            foreach ([[$from, $to], [$to, $from]] as [$a, $b]) {
                $edges[] = ['from_node_code' => $a, 'to_node_code' => $b, 'length_m' => $length, 'expected_seconds' => round($length / 1.2, 2), 'is_accessible' => true, 'status' => 'open', 'connector' => null];
            }
        };
        foreach ([
            ['G-C01', 'G-N0001'], ['G-N0001', 'G-N0002'], ['G-N0001', 'G-N0003'], ['G-N0002', 'E-G-101'],
            ['G-N0003', 'E-G-101-B'], ['G-N0003', 'G-F-Toilets-G'], ['G-C01', 'G-X-Central-Lift'], ['G-X-Central-Lift', 'G-N0003'],
            ['G-N0002', 'G-X-Main-Stairs'], ['G-N0001', 'G-X-East-Escalator'],
            ['1-X-Central-Lift', '1-C01'], ['1-C01', '1-N0001'], ['1-N0001', '1-N0002'], ['1-N0002', 'E-1-205'],
            ['1-N0002', '1-F-Toilets-1'], ['1-X-Central-Lift', '1-F-Prayer-Room'], ['1-X-Main-Stairs', '1-N0002'], ['1-X-East-Escalator', '1-N0002'],
        ] as [$from, $to]) {
            $walk($from, $to);
        }
        foreach ([['G-X-Central-Lift', '1-X-Central-Lift'], ['1-X-Central-Lift', 'G-X-Central-Lift']] as [$a, $b]) {
            $edges[] = ['from_node_code' => $a, 'to_node_code' => $b, 'length_m' => 5.0, 'expected_seconds' => 35.0, 'is_accessible' => true, 'status' => 'open', 'connector' => 'Central Lift'];
        }
        foreach ([['G-X-Main-Stairs', '1-X-Main-Stairs'], ['1-X-Main-Stairs', 'G-X-Main-Stairs']] as [$a, $b]) {
            $edges[] = ['from_node_code' => $a, 'to_node_code' => $b, 'length_m' => 8.0, 'expected_seconds' => 12.5, 'is_accessible' => false, 'status' => 'open', 'connector' => 'Main Stairs'];
        }
        $edges[] = ['from_node_code' => 'G-X-East-Escalator', 'to_node_code' => '1-X-East-Escalator', 'length_m' => 7.0, 'expected_seconds' => 10.0, 'is_accessible' => false, 'status' => 'open', 'connector' => 'East Escalator'];

        $floor = fn (string $code, string $name, float $elevation): array => [
            'floor_code' => $code,
            'name' => $name,
            'elevation_m' => $elevation,
            'plan' => ['file' => "floors/{$code}_plan.png", 'width_px' => 500, 'height_px' => 400, 'pixel_origin_x' => 0, 'pixel_origin_y' => 0, 'metres_per_pixel' => 0.05, 'rotation_deg' => 0, 'world_origin_x_m' => -2.0, 'world_origin_y_m' => 14.0],
            'graph_preview_file' => "floors/{$code}_graph_preview.png",
            'model' => ['file' => "floors/{$code}_model.glb", 'triangles' => 2, 'draco' => false],
        ];

        return [
            'format' => 'ism-map-package',
            'format_version' => 1,
            'generated_at' => '2026-11-24T09:00:00+00:00',
            'coordinate_frame' => 'ism-mall-local-v1: metres; right-handed; x and y horizontal, z up',
            'floors' => [$floor('G', 'Ground floor', 0.0), $floor('1', 'First floor', 5.0)],
            'nodes' => $nodeRows,
            'edges' => $edges,
            'connectors' => [
                ['name' => 'Central Lift', 'connector_type' => 'elevator', 'direction' => 'both', 'is_accessible' => true, 'stops' => [['floor_code' => 'G', 'node_code' => 'G-X-Central-Lift'], ['floor_code' => '1', 'node_code' => '1-X-Central-Lift']]],
                ['name' => 'Main Stairs', 'connector_type' => 'stairs', 'direction' => 'both', 'is_accessible' => false, 'stops' => [['floor_code' => 'G', 'node_code' => 'G-X-Main-Stairs'], ['floor_code' => '1', 'node_code' => '1-X-Main-Stairs']]],
                ['name' => 'East Escalator', 'connector_type' => 'escalator', 'direction' => 'up', 'is_accessible' => false, 'stops' => [['floor_code' => 'G', 'node_code' => 'G-X-East-Escalator'], ['floor_code' => '1', 'node_code' => '1-X-East-Escalator']]],
            ],
            'checkpoints' => [
                ['checkpoint_code' => 'G-C01', 'label' => 'Main entrance, pillar facing the doors', 'floor_code' => 'G', 'node_code' => 'G-C01', 'qr' => ['x_m' => -0.8, 'y_m' => 0.0, 'z_m' => 1.4, 'yaw_deg' => 0.0, 'marker_height_m' => 1.4, 'physical_width_m' => 0.18]],
                ['checkpoint_code' => '1-C01', 'label' => 'First floor, by the lift', 'floor_code' => '1', 'node_code' => '1-C01', 'qr' => ['x_m' => 5.0, 'y_m' => 10.8, 'z_m' => 6.4, 'yaw_deg' => 270.0, 'marker_height_m' => 1.4, 'physical_width_m' => 0.18]],
            ],
            'shop_entrances' => [
                ['unit_code' => 'G-101', 'floor_code' => 'G', 'node_code' => 'E-G-101', 'is_primary' => true],
                ['unit_code' => 'G-101', 'floor_code' => 'G', 'node_code' => 'E-G-101-B', 'is_primary' => false],
                ['unit_code' => '1-205', 'floor_code' => '1', 'node_code' => 'E-1-205', 'is_primary' => true],
            ],
            'facility_anchors' => [
                ['facility_name' => 'Toilets G', 'facility_type' => 'toilet', 'floor_code' => 'G', 'node_code' => 'G-F-Toilets-G'],
                ['facility_name' => 'Toilets 1', 'facility_type' => 'toilet', 'floor_code' => '1', 'node_code' => '1-F-Toilets-1'],
                ['facility_name' => 'Prayer room', 'facility_type' => 'prayer_room', 'floor_code' => '1', 'node_code' => '1-F-Prayer-Room'],
            ],
            'stats' => ['floors' => 2, 'nodes' => count($nodeRows), 'edges' => count($edges), 'checkpoints' => 2, 'shop_entrances' => 3, 'facility_anchors' => 3, 'connectors' => 3],
        ];
    }
}
