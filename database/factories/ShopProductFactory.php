<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopProduct>
 */
class ShopProductFactory extends Factory
{
    protected $model = ShopProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'variant_id' => ProductVariant::factory(),
            'seller_sku' => fake()->unique()->bothify('SKU-####'),
            'price_amount' => fake()->randomFloat(2, 20, 5000),
            'currency' => 'BDT',
            'availability_status' => 'available',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the listing has been withdrawn.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
