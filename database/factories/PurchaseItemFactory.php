<?php

namespace Database\Factories;

use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /**
     * Define the model's default state: one unit at 100.00. Prefer PurchaseFactory::withLine(),
     * which also keeps the purchase total and the listing's shop consistent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'line_no' => 1,
            'shop_product_id' => ShopProduct::factory(),
            'product_label_snapshot' => fake()->words(2, true),
            'quantity' => 1,
            'unit_price_amount' => '100.00',
            'line_discount_amount' => '0.00',
            'line_total_amount' => '100.00',
        ];
    }
}
