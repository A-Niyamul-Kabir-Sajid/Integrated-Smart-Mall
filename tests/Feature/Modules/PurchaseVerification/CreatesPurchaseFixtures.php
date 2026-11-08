<?php

namespace Tests\Feature\Modules\PurchaseVerification;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;

/**
 * Shared set-up for the PurchaseVerification tests.
 */
trait CreatesPurchaseFixtures
{
    protected function staffOf(Shop $shop, RoleCode $role = RoleCode::ShopStaff): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantShopRole($user, $shop, $role);

        return $user;
    }

    protected function assertRuleViolated(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }
}
