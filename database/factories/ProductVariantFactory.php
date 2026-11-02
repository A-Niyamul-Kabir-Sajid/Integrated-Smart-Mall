<?php

namespace Database\Factories;

use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_code' => 'default',
            'label' => 'Standard',
            'attributes_json' => [],
            'barcode' => null,
            'status' => 'active',
        ];
    }
}
