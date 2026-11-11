<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Models\ShoppingList;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    protected $model = ShoppingList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mall_id' => Mall::factory(),
            'name' => fake()->randomElement(['Weekly groceries', 'Birthday party', 'School supplies']),
        ];
    }
}
