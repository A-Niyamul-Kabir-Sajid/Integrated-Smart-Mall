<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Floor>
 */
class FloorFactory extends Factory
{
    protected $model = Floor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(1, 99);

        return [
            'mall_id' => Mall::factory(),
            'code' => 'F'.$level,
            'name' => 'Floor '.$level,
            'sort_order' => $level,
            'elevation_m' => $level * 4.5,
            'status' => 'active',
        ];
    }
}
