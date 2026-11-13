<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopApplication>
 */
class ShopApplicationFactory extends Factory
{
    protected $model = ShopApplication::class;

    /**
     * A pending application for a draft shop; the mall follows the shop.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory()->draft(),
            'mall_id' => fn (array $attributes) => Shop::query()->find($attributes['shop_id'])->mall_id,
            'applicant_user_id' => User::factory(),
            'status' => 'pending',
            'message' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved', 'decided_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'rejected', 'decision_reason' => 'Not a fit.', 'decided_at' => now()]);
    }
}
