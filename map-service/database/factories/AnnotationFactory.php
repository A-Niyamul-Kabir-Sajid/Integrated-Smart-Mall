<?php

namespace Database\Factories;

use App\Models\Annotation;
use App\Models\MallMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Annotation>
 */
class AnnotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mall_map_id' => MallMap::factory(),
            'code' => 'ann-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'kind' => 'shop_sign',
            'floor_code' => 'G',
            'unit_code' => null,
            'text' => fake()->company(),
            'subtitle' => '',
            'background' => '#1f2a44',
            'color' => '#ffffff',
            'image_path' => null,
            'width_m' => 2.4,
            'lift_m' => 0,
            'mount' => 'wall',
            'x_m' => 1.5,
            'y_m' => 2.0,
            'z_m' => 1.4,
            'yaw_deg' => 90,
        ];
    }

    public function advertisement(): static
    {
        return $this->state(['kind' => 'advertisement', 'width_m' => 0.8, 'background' => '#ffffff', 'color' => '#1b1b1b', 'subtitle' => 'Your offer here']);
    }
}
