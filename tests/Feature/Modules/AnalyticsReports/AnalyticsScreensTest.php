<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Models\SearchEvent;
use App\Modules\AnalyticsReports\Models\ViewEvent;
use App\Modules\AnalyticsReports\Services\ActivityReportService;
use App\Modules\AnalyticsReports\Services\ActivityRollupService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopOrderItem;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsScreensTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private Shop $shop;

    private User $owner;

    private ShopProduct $listing;

    private ShopProduct $unviewedListing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $this->admin = $this->userWith(fn (User $user) => app(MembershipService::class)->grantMallRole($user, $this->mall, RoleCode::MallAdmin));
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Cafe Brew']);
        $this->owner = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopOwner));
        $this->listing = ShopProduct::factory()->create(['shop_id' => $this->shop->getKey()]);
        $this->unviewedListing = ShopProduct::factory()->create(['shop_id' => $this->shop->getKey()]);
    }

    private function userWith(callable $grant): User
    {
        $user = User::factory()->create();
        $grant($user);

        return $user;
    }

    private function searches(string $term, int $count, int $results, string $label = 'real', ?Mall $mall = null, string $kind = 'shop'): void
    {
        for ($i = 0; $i < $count; $i++) {
            SearchEvent::query()->create([
                'mall_id' => ($mall ?? $this->mall)->getKey(), 'kind' => $kind, 'term' => $term, 'term_masked' => false, 'result_count' => $results,
                'signed_in' => false, 'dataset_label' => $label, 'occurred_at' => '2026-03-14 05:00:00',
            ]);
        }
    }

    private function views(string $kind, ?string $shopId, ?string $productId, int $visitors, string $label = 'real', string $at = '2026-03-14 05:00:00'): void
    {
        for ($i = 0; $i < $visitors; $i++) {
            ViewEvent::query()->create([
                'mall_id' => $this->mall->getKey(), 'kind' => $kind, 'shop_id' => $shopId, 'product_id' => $productId,
                'visitor_key' => hash('sha256', "v{$i}{$at}{$kind}"), 'dataset_label' => $label, 'occurred_at' => $at,
            ]);
        }
    }

    private function rollup(): void
    {
        app(ActivityRollupService::class)->rollupRange($this->mall, CarbonImmutable::parse('2026-03-01', 'Asia/Dhaka'), CarbonImmutable::parse('2026-03-15', 'Asia/Dhaka'));
    }

    private function order(ShopProduct $listing, string $status = 'requested', ?Shop $shop = null): ShopOrder
    {
        $order = ShopOrder::factory()->create(['shop_id' => ($shop ?? $this->shop)->getKey(), 'status' => $status, 'created_at' => '2026-03-14 07:00:00']);
        ShopOrderItem::query()->create(['shop_order_id' => $order->getKey(), 'line_no' => 1, 'shop_product_id' => $listing->getKey(), 'label_snapshot' => 'Item', 'quantity' => 2, 'unit_price_amount' => '10.00', 'line_total_amount' => '20.00']);

        return $order;
    }

    private function sale(ShopProduct $listing, string $status = 'completed', string $at = '2026-03-14 08:00:00'): Purchase
    {
        return PurchaseFactory::new()->state(['shop_id' => $listing->shop_id, 'status' => $status, 'purchased_at' => $at, 'recorded_at' => $at])->withLine($listing)->create();
    }

    public function test_the_mall_administrator_sees_search_demand_with_unmet_terms_and_counts(): void
    {
        $this->searches('coffee', 5, 3);
        $this->searches('coffee', 2, 0);
        $this->searches('umbrella', 4, 0);
        $this->searches('rice', 1, 8, kind: 'product');
        SearchEvent::query()->create(['mall_id' => $this->mall->getKey(), 'kind' => 'shop', 'term' => null, 'term_masked' => true, 'result_count' => 0, 'signed_in' => false, 'dataset_label' => 'real', 'occurred_at' => '2026-03-14 05:00:00']);
        $this->rollup();

        $page = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', $this->mall));

        $page->assertOk()->assertSee('Searched but not found')->assertSee('umbrella')->assertSee('coffee')->assertSee('Real visitor traffic');
        $report = $page->viewData('report');
        $this->assertSame(13, $report['searches']);
        $this->assertSame(7, $report['zero_results']);
        $this->assertSame(1, $report['masked']);
        $this->assertSame(['umbrella', 'coffee'], $report['unmet']->pluck('term')->all());
        $this->assertSame([4, 2], $report['unmet']->pluck('zero_results')->all());
        $this->assertSame(['coffee', 'umbrella', 'rice'], $report['top_terms']->pluck('term')->all());
        $this->assertSame(7, $report['top_terms']->first()['searches']);
        $this->assertSame(2, $report['top_terms']->first()['zero_results']);
    }

    public function test_the_search_kind_and_period_filters_apply(): void
    {
        $this->searches('coffee', 3, 1);
        $this->searches('rice', 2, 1, kind: 'product');
        $this->rollup();

        $product = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'kind' => 'product']))->viewData('report');
        $this->assertSame(2, $product['searches']);
        $this->assertSame(['rice'], $product['top_terms']->pluck('term')->all());

        $outside = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'from' => '2026-03-01', 'to' => '2026-03-13']));
        $outside->assertOk()->assertSee('No searches were recorded in this period.');
        $this->assertSame(0, $outside->viewData('report')['searches']);

        $edge = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'from' => '2026-03-14', 'to' => '2026-03-14']))->viewData('report');
        $this->assertSame(5, $edge['searches'], 'Both ends of the period are included.');
    }

    public function test_demonstration_traffic_is_shown_only_when_asked_for_and_is_labelled(): void
    {
        $this->searches('coffee', 3, 1);
        $this->searches('gift card', 9, 0, 'demo');
        $this->rollup();

        $real = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', $this->mall));
        $real->assertDontSee('gift card');
        $this->assertSame(3, $real->viewData('report')['searches']);

        $demo = $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'dataset' => 'demo']));
        $demo->assertOk()->assertSee('gift card')->assertSee('Demonstration data')->assertDontSee('>coffee<', false);
        $this->assertSame(9, $demo->viewData('report')['searches']);
    }

    public function test_another_malls_traffic_never_appears(): void
    {
        $other = Mall::factory()->create();
        $this->searches('secretterm', 5, 0, mall: $other);
        $this->searches('coffee', 1, 1);
        app(ActivityRollupService::class)->rollupRange($other, CarbonImmutable::parse('2026-03-14'), CarbonImmutable::parse('2026-03-14'));
        $this->rollup();

        $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', $this->mall))->assertOk()->assertDontSee('secretterm');
    }

    public function test_bad_periods_are_refused_with_a_message(): void
    {
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'from' => '2026-03-10', 'to' => '2026-03-01']))->assertSessionHasErrors('business');
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'from' => '2024-01-01', 'to' => '2026-03-01']))->assertSessionHasErrors('business');
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'from' => 'yesterday']))->assertSessionHasErrors('from');
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.search', [$this->mall, 'dataset' => 'invented']))->assertSessionHasErrors('dataset');
    }

    public function test_the_mall_wide_views_screen_lists_shops_products_and_days(): void
    {
        $productId = $this->listing->variant->product_id;
        $this->views('shop', $this->shop->getKey(), null, 4);
        $this->views('shop', $this->shop->getKey(), null, 2, at: '2026-03-13 05:00:00');
        $this->views('product', null, $productId, 3);
        $this->rollup();

        $page = $this->actingAs($this->admin)->get(route('mall-admin.analytics.views', $this->mall));

        $page->assertOk()->assertSee('Cafe Brew');
        $report = $page->viewData('report');
        $this->assertSame(6, $report['shop_views']);
        $this->assertSame(6, $report['shop_visitors'], 'Distinct visitors per day, added over two days.');
        $this->assertSame(['name' => 'Cafe Brew', 'views' => 6, 'visitors' => 6], $report['shops']->first());
        $this->assertSame(3, $report['products']->first()['views']);
        $this->assertSame(4, $report['daily']['2026-03-14']);
        $this->assertSame(2, $report['daily']['2026-03-13']);
        $this->assertSame(0, $report['daily']['2026-03-12'], 'Quiet days show zero.');
        $this->assertCount(30, $report['daily']);
    }

    public function test_search_and_view_screens_are_closed_to_everyone_but_the_malls_administrators(): void
    {
        $otherAdmin = $this->userWith(fn (User $user) => app(MembershipService::class)->grantMallRole($user, Mall::factory()->create(), RoleCode::MallAdmin));
        $customer = User::factory()->create();
        $urls = [route('mall-admin.analytics.search', $this->mall), route('mall-admin.analytics.views', $this->mall)];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($urls as $url) {
            foreach ([$customer, $this->owner, $otherAdmin] as $intruder) {
                $this->actingAs($intruder)->get($url)->assertForbidden();
            }

            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->expectException(AuthorizationException::class);
        app(ActivityReportService::class)->searchDemand($this->owner, $this->mall, ReportPeriod::fromInput($this->mall, null, null), DatasetLabel::Real);
    }

    public function test_the_shop_owner_sees_their_own_views_and_a_funnel_that_reconciles_with_orders_and_sales(): void
    {
        $productId = $this->listing->variant->product_id;
        $this->views('shop', $this->shop->getKey(), null, 5);
        $this->views('product', null, $productId, 10);
        $this->order($this->listing);
        $this->order($this->listing, 'completed');
        $this->order($this->listing, 'cancelled');
        $this->sale($this->listing);
        $this->sale($this->listing);
        $this->sale($this->listing, 'voided');
        $this->sale($this->listing, 'completed', '2026-01-01 08:00:00');
        $this->rollup();

        $page = $this->actingAs($this->owner)->get(route('workspace.analytics.index', $this->shop));

        $page->assertOk()->assertSee('Visitors and views');
        $report = $page->viewData('report');
        $this->assertSame(5, $report['shop_views']);

        $row = $report['funnel']->firstWhere('product_views', 10);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['orders'], 'Every order that contains the listing, whatever its status.');
        $this->assertSame(2, $row['sales'], 'Completed sales in the period only: not voided, not outside the period.');
        $this->assertSame(30.0, $row['conversion']);

        $quiet = $report['funnel']->firstWhere('product_views', 0);
        $this->assertNull($quiet['conversion'], 'No views, so no rate (not zero and not infinite).');
        $page->assertSee('—');
    }

    public function test_the_funnel_ignores_other_shops_orders_even_for_the_same_product(): void
    {
        $rival = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $rivalListing = ShopProduct::factory()->create(['shop_id' => $rival->getKey(), 'variant_id' => $this->listing->variant_id]);
        $this->views('product', null, $this->listing->variant->product_id, 4);
        $this->order($rivalListing, shop: $rival);
        $this->order($rivalListing, shop: $rival);
        $this->sale($rivalListing);
        $this->order($this->listing);
        $this->rollup();

        $report = $this->actingAs($this->owner)->get(route('workspace.analytics.index', $this->shop))->viewData('report');
        $row = $report['funnel']->firstWhere('product_views', 4);

        $this->assertSame(1, $row['orders']);
        $this->assertSame(0, $row['sales']);
        $this->assertSame(25.0, $row['conversion']);
    }

    public function test_demonstration_views_are_not_matched_with_real_orders(): void
    {
        $this->views('product', null, $this->listing->variant->product_id, 6, 'demo');
        $this->order($this->listing);
        $this->rollup();

        $page = $this->actingAs($this->owner)->get(route('workspace.analytics.index', [$this->shop, 'dataset' => 'demo']));

        $page->assertOk()->assertSee('are not matched with real orders');
        $report = $page->viewData('report');
        $this->assertFalse($report['funnel_available']);
        $this->assertSame(0, $report['funnel']->sum('orders'));
        $this->assertNull($report['funnel']->firstWhere('product_views', 6)['conversion']);
    }

    public function test_a_shop_never_sees_another_shops_views(): void
    {
        $rival = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Rival Rugs']);
        $rivalOwner = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $rival, RoleCode::ShopOwner));
        $this->views('shop', $rival->getKey(), null, 9);
        $this->views('shop', $this->shop->getKey(), null, 2);
        $this->rollup();

        $own = $this->actingAs($this->owner)->get(route('workspace.analytics.index', $this->shop));
        $this->assertSame(2, $own->viewData('report')['shop_views']);
        $own->assertDontSee('Rival Rugs');

        $this->actingAs($rivalOwner)->get(route('workspace.analytics.index', $this->shop))->assertForbidden();
        $this->assertSame(9, $this->actingAs($rivalOwner)->get(route('workspace.analytics.index', $rival))->viewData('report')['shop_views']);
    }

    public function test_only_the_shops_owner_or_the_platform_may_open_the_shop_screen(): void
    {
        $staff = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopStaff));
        $customer = User::factory()->create();
        $platform = $this->userWith(fn (User $user) => app(RoleAssignmentService::class)->assignPlatformRole($user, RoleCode::PlatformAdmin));
        $url = route('workspace.analytics.index', $this->shop);

        $this->get($url)->assertRedirect(route('login'));

        foreach ([$customer, $staff, $this->admin] as $intruder) {
            $this->actingAs($intruder)->get($url)->assertForbidden();
        }

        $this->actingAs($this->owner)->get($url)->assertOk();
        $this->actingAs($platform)->get($url)->assertOk();
    }

    public function test_the_dashboards_link_to_the_new_screens(): void
    {
        $this->actingAs($this->owner)->get(route('workspace.dashboard', $this->shop))->assertOk()->assertSee(route('workspace.analytics.index', $this->shop), false);
        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))->assertOk()
            ->assertSee(route('mall-admin.analytics.search', $this->mall), false)->assertSee(route('mall-admin.analytics.views', $this->mall), false);
    }
}
