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
}
