<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseFactory;
use Database\Factories\ShopOrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared fixture of the EPIC-12 tests: a mall in Dhaka (UTC+6), two shops with owners, a mall administrator
 * and a customer. The clock stands at 2026-03-15 12:00 UTC (18:00 in Dhaka). Hand-calculated expected values
 * live in the tests, built from the records created here.
 */
abstract class ReportTestCase extends TestCase
{
    use RefreshDatabase;

    protected Mall $mall;

    protected User $admin;

    protected Shop $shop;

    protected User $owner;

    protected Shop $otherShop;

    protected User $otherOwner;

    protected User $customer;

    protected ShopProduct $listingA;

    protected ShopProduct $listingB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $this->admin = $this->userWith(fn (User $user) => app(MembershipService::class)->grantMallRole($user, $this->mall, RoleCode::MallAdmin));
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Cafe Brew', 'status' => 'active']);
        $this->owner = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopOwner));
        $this->otherShop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Book Nook', 'status' => 'active']);
        $this->otherOwner = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $this->otherShop, RoleCode::ShopOwner));
        $this->customer = User::factory()->create();
        $this->listingA = ShopProduct::factory()->create(['shop_id' => $this->shop->getKey(), 'price_amount' => '100.00']);
        $this->listingB = ShopProduct::factory()->create(['shop_id' => $this->shop->getKey(), 'price_amount' => '250.00']);
    }

    protected function userWith(callable $grant): User
    {
        $user = User::factory()->create();
        $grant($user);

        return $user;
    }

    /**
     * A sale with the given lines [[listing, quantity], ...] made at a UTC time.
     *
     * @param  list<array{0: ShopProduct, 1: int}>  $lines
     */
    protected function sale(Shop $shop, array $lines, string $at, bool $voided = false, ?string $receipt = null): Purchase
    {
        $factory = PurchaseFactory::new()->state(array_filter([
            'shop_id' => $shop->getKey(), 'purchased_at' => $at, 'recorded_at' => $at, 'external_receipt_ref' => $receipt,
        ]));

        foreach ($lines as [$listing, $quantity]) {
            $factory = $factory->withLine($listing, $quantity);
        }

        return ($voided ? $factory->voided() : $factory)->create();
    }

    protected function order(Shop $shop, string $status, string $kind, string $at, ?string $number = null): ShopOrder
    {
        return ShopOrderFactory::new()->status($status)->state(array_filter([
            'shop_id' => $shop->getKey(), 'customer_user_id' => $this->customer->getKey(), 'kind' => $kind, 'created_at' => $at, 'updated_at' => $at,
            'completed_at' => $status === 'completed' ? $at : null, 'order_number' => $number,
        ]))->create();
    }

    protected function review(Shop $shop, int $rating, string $at, string $body = 'A review', string $status = 'published'): Review
    {
        return Review::factory()->rated($rating)->create(['shop_id' => $shop->getKey(), 'created_at' => $at, 'updated_at' => $at, 'body' => $body, 'title' => null, 'status' => $status]);
    }

    protected function complaint(string $number, string $category, ?Shop $shop, string $status, string $filedAt, ?string $closedAt = null): void
    {
        DB::table('complaints')->insert([
            'complaint_id' => (string) Str::uuid(), 'complaint_number' => $number, 'mall_id' => $this->mall->getKey(), 'customer_user_id' => $this->customer->getKey(),
            'shop_id' => $shop?->getKey(), 'category' => $category, 'subject' => 'Subject '.$number, 'description' => 'Description of '.$number, 'status' => $status,
            'created_at' => $filedAt, 'updated_at' => $closedAt ?? $filedAt, 'closed_at' => $closedAt,
        ]);
    }

    protected function stock(ShopProduct $listing, int $onHand, int $threshold = 5): void
    {
        DB::table('listing_stocks')->insert(['shop_product_id' => $listing->getKey(), 'shop_id' => $listing->shop_id, 'on_hand' => $onHand, 'low_stock_threshold' => $threshold, 'updated_at' => now()]);
    }
}
