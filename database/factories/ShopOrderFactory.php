<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopOrder>
 */
class ShopOrderFactory extends Factory
{
    protected $model = ShopOrder::class;

    /**
     * A requested order with no lines; use OrderService::place() when lines matter.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'shop_id' => Shop::factory(),
            'customer_user_id' => User::factory(),
            'kind' => 'reservation',
            'status' => 'requested',
            'currency' => 'BDT',
            'subtotal_amount' => '100.00',
            'discount_amount' => '0.00',
            'total_amount' => '100.00',
            'pickup_code' => Str::upper(Str::random(6)),
            'pickup_by' => now()->addDay(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
