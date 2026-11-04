<?php

namespace Database\Seeders;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\Seeder;

/**
 * One demonstration account per role, all with the password "password".
 * Requires DemoMallSeeder. Never run against real data.
 */
class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(RoleAssignmentService $roles, MembershipService $memberships): void
    {
        $mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->firstOrFail();
        $cafe = Shop::query()->whereBelongsTo($mall)->where('slug', 'cafe-brew')->firstOrFail();

        $roles->assignPlatformRole($this->account('Platform Admin', 'platform@demo.test'), RoleCode::PlatformAdmin);
        $memberships->grantMallRole($this->account('Mall Admin', 'mall.admin@demo.test'), $mall, RoleCode::MallAdmin);
        $memberships->grantShopRole($this->account('Cafe Brew Owner', 'owner@demo.test'), $cafe, RoleCode::ShopOwner);
        $memberships->grantShopRole($this->account('Cafe Brew Staff', 'staff@demo.test'), $cafe, RoleCode::ShopStaff);
        $roles->assignPlatformRole($this->account('Demo Customer', 'customer@demo.test'), RoleCode::Customer);
    }

    private function account(string $name, string $email): User
    {
        return User::query()->create([
            'display_name' => $name,
            'email' => $email,
            'password_hash' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);
    }
}
