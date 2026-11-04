<?php

namespace Tests\Feature\Modules\AccountsAccess\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_a_mall_role_is_idempotent_and_audited_in_the_mall_scope(): void
    {
        $user = User::factory()->create();
        $mall = Mall::factory()->create();
        $service = app(MembershipService::class);

        $service->grantMallRole($user, $mall, RoleCode::MallAdmin);
        $service->grantMallRole($user, $mall, RoleCode::MallAdmin);

        $this->assertTrue($user->hasMallRole($mall->getKey(), RoleCode::MallAdmin));
        $this->assertSame(1, $user->mallMemberships()->count());
        $audit = AuditLog::query()->sole();
        $this->assertSame(['role' => 'mall_admin', 'scope' => 'mall'], $audit->metadata);
        $this->assertSame($mall->getKey(), $audit->mall_id);
    }

    public function test_revoking_keeps_the_row_and_granting_again_reactivates_it(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $service = app(MembershipService::class);
        $service->grantShopRole($user, $shop, RoleCode::ShopStaff);

        $service->revokeShopRole($user, $shop, RoleCode::ShopStaff);
        $this->assertFalse($user->hasShopRole($shop->getKey(), RoleCode::ShopStaff));
        $this->assertSame('revoked', $user->shopMemberships()->sole()->status);

        $service->grantShopRole($user, $shop, RoleCode::ShopStaff);
        $this->assertTrue($user->hasShopRole($shop->getKey(), RoleCode::ShopStaff));
        $this->assertSame(1, $user->shopMemberships()->count());
        $this->assertEqualsCanonicalizing(
            ['access.role_assigned', 'access.role_revoked', 'access.role_assigned'],
            AuditLog::query()->pluck('action')->all(),
        );
        $this->assertSame([$shop->getKey()], AuditLog::query()->distinct()->pluck('shop_id')->all());
    }

    public function test_revoking_a_role_that_is_not_held_changes_nothing(): void
    {
        app(MembershipService::class)->revokeMallRole(User::factory()->create(), Mall::factory()->create(), RoleCode::MallAdmin);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_roles_must_match_the_membership_scope(): void
    {
        $user = User::factory()->create();
        $service = app(MembershipService::class);

        foreach ([
            'mall_membership.wrong_role_scope' => fn () => $service->grantMallRole($user, Mall::factory()->create(), RoleCode::ShopOwner),
            'shop_membership.wrong_role_scope' => fn () => $service->grantShopRole($user, Shop::factory()->create(), RoleCode::MallAdmin),
        ] as $rule => $grant) {
            try {
                $grant();
                $this->fail("Expected {$rule}.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($rule, $violation->rule);
            }
        }

        $this->assertSame(0, $user->mallMemberships()->count() + $user->shopMemberships()->count());
    }
}
