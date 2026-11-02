<?php

namespace Database\Factories;

use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => fake()->unique()->words(3, true),
            'brand' => fake()->company(),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
