<?php

namespace Database\Factories;

use App\Models\Checkpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Checkpoint>
 */
class CheckpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mall_ref' => 'demo-mall',
            'checkpoint_code' => 'G-C'.fake()->unique()->numberBetween(10, 99),
            'public_slug' => Str::lower(Str::random(12)),
            'label' => fake()->sentence(3),
            'status' => 'active',
        ];
    }

    public function retired(): static
    {
        return $this->state(['status' => 'retired', 'retired_at' => now()]);
    }
}
