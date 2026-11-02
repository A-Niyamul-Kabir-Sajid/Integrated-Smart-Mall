<?php

namespace Tests\Feature\Modules\AccountsAccess\Models;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\Role;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_mall_roles_apply_only_to_the_mall_of_an_active_membership(): void
    {
        $user = User::factory()->create();
        [$mine, $other, $revoked] = Mall::factory()->count(3)->create();
        $user->mallMemberships()->create(['mall_id' => $mine->getKey(), 'role_id' => Role::forCode(RoleCode::MallAdmin)->getKey(), 'status' => 'active', 'assigned_at' => now()]);
        $user->mallMemberships()->create(['mall_id' => $revoked->getKey(), 'role_id' => Role::forCode(RoleCode::MallAdmin)->getKey(), 'status' => 'revoked', 'assigned_at' => now()]);

        $this->assertTrue($user->hasMallRole($mine->getKey(), RoleCode::MallAdmin));
        $this->assertFalse($user->hasMallRole($other->getKey(), RoleCode::MallAdmin));
        $this->assertFalse($user->hasMallRole($revoked->getKey(), RoleCode::MallAdmin));
    }

    public function test_shop_roles_match_any_of_the_given_codes(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $user->shopMemberships()->create(['shop_id' => $shop->getKey(), 'role_id' => Role::forCode(RoleCode::ShopStaff)->getKey(), 'status' => 'active', 'assigned_at' => now()]);

        $this->assertTrue($user->hasShopRole($shop->getKey(), RoleCode::ShopOwner, RoleCode::ShopStaff));
        $this->assertFalse($user->hasShopRole($shop->getKey(), RoleCode::ShopOwner));
    }

    public function test_a_user_holds_a_role_for_a_shop_at_most_once(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $row = ['shop_id' => $shop->getKey(), 'role_id' => Role::forCode(RoleCode::ShopOwner)->getKey(), 'status' => 'active', 'assigned_at' => now()];
        $user->shopMemberships()->create($row);

        $this->expectException(QueryException::class);

        $user->shopMemberships()->create($row);
    }
}
