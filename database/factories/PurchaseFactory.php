<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state. The total is 0.00 until lines are added with withLine().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchasedAt = fake()->dateTimeBetween('-30 days', '-1 hour');

        return [
            'shop_id' => Shop::factory(),
            'buyer_user_id' => null,
            'external_receipt_ref' => fake()->unique()->bothify('RCPT-######'),
            'status' => PurchaseStatus::Completed,
            'currency' => 'BDT',
            'total_amount' => '0.00',
            'purchased_at' => $purchasedAt,
            'recorded_at' => $purchasedAt,
        ];
    }

    /**
     * A sale made by a known customer account.
     */
    public function forBuyer(User $buyer): static
    {
        return $this->state(fn (array $attributes) => ['buyer_user_id' => $buyer->getKey()]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PurchaseStatus::Voided]);
    }

    /**
     * Adds one line for a listing of the purchase's shop and keeps the total in step.
     * With no listing given, a listing of that shop is created.
     */
    public function withLine(?ShopProduct $listing = null, int $quantity = 1, string $discount = '0.00'): static
    {
        return $this->afterCreating(function (Purchase $purchase) use ($listing, $quantity, $discount): void {
            $listing ??= ShopProduct::factory()->for($purchase->shop)->create();

            $lineTotal = number_format($quantity * (float) $listing->price_amount - (float) $discount, 2, '.', '');

            PurchaseItemFactory::new()->for($purchase)->create([
                'line_no' => ((int) $purchase->items()->max('line_no')) + 1,
                'shop_product_id' => $listing->getKey(),
                'quantity' => $quantity,
                'unit_price_amount' => $listing->price_amount,
                'line_discount_amount' => $discount,
                'line_total_amount' => $lineTotal,
            ]);

            $total = (string) $purchase->items()->sum('line_total_amount');
            $purchase->forceFill(['total_amount' => $total])->saveQuietly();
        });
    }
}
