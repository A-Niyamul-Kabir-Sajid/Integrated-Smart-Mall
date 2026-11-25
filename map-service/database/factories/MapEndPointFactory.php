<?php

namespace Database\Factories;

use App\Models\MapEndPoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A point row that is not applied yet; MapEditService::createPoint makes routable ones.
 *
 * @extends Factory<MapEndPoint>
 */
class MapEndPointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::lower(Str::random(10)),
            'name' => fake()->words(2, true),
            'floor_code' => 'G',
            'x_m' => 5.0,
            'y_m' => 1.0,
            'z_m' => 0.0,
        ];
    }
}
