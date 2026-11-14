<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPromotion>
 */
class ShopPromotionFactory extends Factory
{
    protected $model = ShopPromotion::class;

    /**
     * A running 10 percent storewide offer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => 'percent',
            'discount_value' => '10.00',
            'coupon_code' => null,
            'min_subtotal' => '0.00',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => null,
            'per_customer_limit' => 1,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function coupon(string $code = 'SAVE10'): static
    {
        return $this->state(fn (array $attributes) => ['coupon_code' => $code]);
    }

    public function amountOff(string $amount): static
    {
        return $this->state(fn (array $attributes) => ['discount_type' => 'amount_off', 'discount_value' => $amount]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
    }
}
