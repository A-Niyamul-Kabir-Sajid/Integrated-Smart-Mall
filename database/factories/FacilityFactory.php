<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'floor_id' => Floor::factory(),
            'name' => fake()->randomElement(['Washroom', 'Prayer Room', 'Information Desk', 'Main Exit', 'ATM Corner']),
            'facility_type' => 'toilet',
            'is_accessible' => true,
            'status' => 'open',
            'location_note' => null,
        ];
    }

    public function temporarilyClosed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'temporarily_closed']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
