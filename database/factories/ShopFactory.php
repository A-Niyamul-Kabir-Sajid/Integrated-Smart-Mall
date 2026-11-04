<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'mall_id' => Mall::factory(),
            'shop_category_id' => ShopCategory::factory(),
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'contact_phone' => '+8801'.fake()->numerify('#########'),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the shop has not been approved for the public directory.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'closed']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
