<?php

namespace Tests\Feature\Seeders;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoMallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_one_signed_in_capable_account_per_role(): void
    {
        $this->seed([DemoMallSeeder::class, DemoAccountsSeeder::class]);
        $mall = Mall::query()->sole();
        $cafe = Shop::query()->where('slug', 'cafe-brew')->sole();
        $account = fn (string $email): User => User::query()->where('email', $email)->sole();

        $this->assertTrue(Gate::forUser($account('platform@demo.test'))->allows(Ability::AdministerPlatform));
        $this->assertTrue(Gate::forUser($account('mall.admin@demo.test'))->allows(Ability::AdministerMall, $mall));
        $this->assertTrue(Gate::forUser($account('owner@demo.test'))->allows(Ability::ManageShop, $cafe));
        $this->assertTrue(Gate::forUser($account('staff@demo.test'))->allows(Ability::WorkInShop, $cafe));
        $this->assertFalse(Gate::forUser($account('staff@demo.test'))->allows(Ability::ManageShop, $cafe));
        $this->assertTrue(auth()->attempt(['email' => 'customer@demo.test', 'password' => DemoAccountsSeeder::PASSWORD]));
    }
}
