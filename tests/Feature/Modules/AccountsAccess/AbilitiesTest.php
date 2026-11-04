<?php

namespace Tests\Feature\Modules\AccountsAccess;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The complete permission matrix: every role against every ability, for the
 * user's own mall/shop and for another one.
 */
class AbilitiesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Expected access per role: [administer-platform, administer-mall (own), manage-shop (own), work-in-shop (own)].
     *
     * @return array<string, array{0: string, 1: array{0: bool, 1: bool, 2: bool, 3: bool}}>
     */
    public static function matrix(): array
    {
        return [
            'customer' => ['customer', [false, false, false, false]],
            'shop staff' => ['shop_staff', [false, false, false, true]],
            'shop owner' => ['shop_owner', [false, false, true, true]],
            'mall admin' => ['mall_admin', [false, true, false, false]],
            'platform admin' => ['platform_admin', [true, true, true, true]],
        ];
    }

    /**
     * @param  array{0: bool, 1: bool, 2: bool, 3: bool}  $expected
     */
    #[DataProvider('matrix')]
    public function test_role_grants_exactly_its_abilities_and_only_in_its_own_mall_or_shop(string $role, array $expected): void
    {
        $mall = Mall::factory()->create();
        $shop = Shop::factory()->for($mall)->create();
        $otherMall = Mall::factory()->create();
        $otherShop = Shop::factory()->for($otherMall)->create();
        $user = User::factory()->create();
        $code = RoleCode::from($role);

        match ($code) {
            RoleCode::Customer, RoleCode::PlatformAdmin => app(RoleAssignmentService::class)->assignPlatformRole($user, $code),
            RoleCode::MallAdmin => app(MembershipService::class)->grantMallRole($user, $mall, $code),
            RoleCode::ShopOwner, RoleCode::ShopStaff => app(MembershipService::class)->grantShopRole($user, $shop, $code),
        };

        $gate = Gate::forUser($user);
        $this->assertSame($expected, [
            $gate->allows(Ability::AdministerPlatform),
            $gate->allows(Ability::AdministerMall, $mall),
            $gate->allows(Ability::ManageShop, $shop),
            $gate->allows(Ability::WorkInShop, $shop),
        ]);

        $isPlatformAdmin = $code === RoleCode::PlatformAdmin;
        $this->assertSame($isPlatformAdmin, $gate->allows(Ability::AdministerMall, $otherMall), 'Mall access must not leak to another mall.');
        $this->assertSame($isPlatformAdmin, $gate->allows(Ability::WorkInShop, $otherShop), 'Shop access must not leak to another shop.');
    }

    public function test_suspended_accounts_lose_every_ability(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();
        app(RoleAssignmentService::class)->assignPlatformRole($user, RoleCode::PlatformAdmin);
        app(MembershipService::class)->grantShopRole($user, $shop, RoleCode::ShopOwner);

        $user->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse(Gate::forUser($user->fresh())->any([Ability::AdministerPlatform, Ability::ManageShop], $shop));
    }

    public function test_revoked_memberships_grant_nothing(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();
        $memberships = app(MembershipService::class);
        $memberships->grantShopRole($user, $shop, RoleCode::ShopOwner);

        $memberships->revokeShopRole($user, $shop, RoleCode::ShopOwner);

        $this->assertTrue(Gate::forUser($user)->denies(Ability::ManageShop, $shop));
    }

    public function test_guests_have_no_abilities(): void
    {
        $this->assertTrue(Gate::denies(Ability::ManageShop, Shop::factory()->create()));
    }
}
