<?php

namespace Database\Factories;

use App\Models\MapPath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A path row that is not applied yet; MapEditService::createPath applies it.
 *
 * @extends Factory<MapPath>
 */
class MapPathFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::lower(Str::random(10)),
            'name' => null,
            'floor_code' => 'G',
            'points' => [[0.0, 0.0], [3.0, 0.0]],
            'is_accessible' => true,
            'one_way' => false,
        ];
    }
}
