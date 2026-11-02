<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mall>
 */
class MallFactory extends Factory
{
    protected $model = Mall::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Mall';

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'address' => fake()->streetAddress(),
            'city' => 'Khulna',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the mall is not yet published.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    /**
     * Indicate that the mall has been retired.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
