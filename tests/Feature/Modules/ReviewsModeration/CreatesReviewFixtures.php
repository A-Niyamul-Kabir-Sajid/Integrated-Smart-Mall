<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Services\VerificationCredentialService;

/**
 * Shared set-up for the ReviewsModeration tests.
 */
trait CreatesReviewFixtures
{
    /**
     * @var array<string, mixed>
     */
    protected array $goodReview = ['rating' => 4, 'title' => 'Good value', 'body' => 'Friendly staff and fair prices.'];

    protected function staffOf(Shop $shop, RoleCode $role = RoleCode::ShopStaff): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantShopRole($user, $shop, $role);

        return $user;
    }

    protected function moderatorOf(Shop $shop): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantMallRole($user, $shop->mall, RoleCode::MallAdmin);

        return $user;
    }

    /**
     * A purchase code the buyer received from the shop for a completed sale of the listing.
     *
     * @return array{0: string, 1: Purchase}
     */
    protected function purchaseCodeFor(User $buyer, ShopProduct $listing): array
    {
        $purchase = Purchase::factory()->for($listing->shop)->forBuyer($buyer)->withLine($listing)->create();
        $secret = app(VerificationCredentialService::class)->issueCredential($this->staffOf($listing->shop), $purchase->items()->first())->secret;

        return [$secret, $purchase];
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
