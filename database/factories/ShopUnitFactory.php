<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopUnit>
 */
class ShopUnitFactory extends Factory
{
    protected $model = ShopUnit::class;

    /**
     * Define the model's default state. The default floor belongs to the
     * same mall as the shop, as the directory business rules require.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'floor_id' => fn (array $attributes) => Floor::factory()->create([
                'mall_id' => Shop::query()->findOrFail($attributes['shop_id'])->mall_id,
            ])->floor_id,
            'unit_code' => fake()->unique()->bothify('U-###'),
            'area_sq_m' => fake()->randomFloat(2, 20, 300),
            'status' => 'open',
        ];
    }
}
