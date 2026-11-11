<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParkingZone>
 */
class ParkingZoneFactory extends Factory
{
    protected $model = ParkingZone::class;

    /**
     * Define the model's default state. The zone's mall follows its floor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'floor_id' => Floor::factory(),
            'mall_id' => fn (array $attributes) => Floor::query()->find($attributes['floor_id'])->mall_id,
            'code' => 'Z'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Zone '.fake()->randomLetter(),
            'capacity' => 60,
            'status' => 'active',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
