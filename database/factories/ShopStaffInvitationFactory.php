<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Models\ShopStaffInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopStaffInvitation>
 */
class ShopStaffInvitationFactory extends Factory
{
    protected $model = ShopStaffInvitation::class;

    /**
     * A pending invitation. Tests that need the raw secret use StaffService::invite().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'email' => fake()->unique()->safeEmail(),
            'staff_function' => 'cashier',
            'secret_hash' => hash('sha256', Str::random(40)),
            'invited_by_user_id' => User::factory(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }
}
