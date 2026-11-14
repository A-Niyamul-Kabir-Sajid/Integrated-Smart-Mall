<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Support\Facades\DB;

/**
 * The EPIC-12 screens over HTTP: who may open what, the date filter, exports and the monthly report pages.
 */
class InsightsScreensTest extends ReportTestCase
{
    private function seedSales(): void
    {
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-03-02 06:00:00');
        $this->sale($this->shop, [[$this->listingB, 1]], '2026-03-03 06:00:00');
        $this->sale($this->shop, [[$this->listingA, 3]], '2026-02-10 06:00:00');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach ([
            route('workspace.insights.index', $this->shop), route('workspace.insights.forecast', $this->shop), route('workspace.insights.export', $this->shop),
            route('workspace.insights.metrics', $this->shop), route('workspace.insights.reports.index', $this->shop),
            route('mall-admin.analytics.index', $this->mall), route('mall-admin.analytics.forecast', $this->mall), route('mall-admin.analytics.export', $this->mall),
            route('mall-admin.analytics.metrics', $this->mall), route('mall-admin.analytics.reports.index', $this->mall),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_the_owner_sees_the_shop_dashboard_with_the_recorded_figures(): void
    {
        $this->seedSales();

        $this->actingAs($this->owner)->get(route('workspace.insights.index', [$this->shop, 'from' => '2026-03-01', 'to' => '2026-03-10']))
            ->assertOk()
            ->assertSee('Recorded sales revenue')
            ->assertSee('BDT 450.00')
            ->assertSee('2 recorded sales')
            ->assertSee('Product performance')
            ->assertSee('How each figure is calculated');
    }

    public function test_the_date_filter_changes_what_is_shown(): void
    {
        $this->seedSales();

        $this->actingAs($this->owner)->get(route('workspace.insights.index', [$this->shop, 'from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertOk()->assertSee('BDT 300.00')->assertDontSee('BDT 450.00');
    }

    public function test_nobody_else_may_open_a_shops_screens(): void
    {
        foreach ([$this->otherOwner, $this->admin, $this->customer] as $user) {
            foreach (['index', 'export', 'forecast', 'metrics', 'reports.index'] as $name) {
                $this->actingAs($user)->get(route('workspace.insights.'.$name, $this->shop))->assertForbidden();
            }

            $this->actingAs($user)->post(route('workspace.insights.reports.store', $this->shop), ['month' => '2026-02'])->assertForbidden();
        }

        $this->assertSame(0, MonthlyReport::query()->count());
    }

    public function test_a_staff_member_without_ownership_is_refused(): void
    {
        $staff = $this->userWith(fn (User $user) => app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopStaff));

        $this->actingAs($staff)->get(route('workspace.insights.index', $this->shop))->assertForbidden();
    }

    public function test_the_mall_administrator_sees_the_mall_dashboard_and_the_navigation_notice(): void
    {
        $this->seedSales();

        $this->actingAs($this->admin)->get(route('mall-admin.analytics.index', [$this->mall, 'from' => '2026-03-01', 'to' => '2026-03-10']))
            ->assertOk()
            ->assertSee('Shop comparison')
            ->assertSee('Cafe Brew')
            ->assertSee('Navigation requests: Not available yet')
            ->assertSee('Nothing here measures how many people walked through the mall');
    }

    public function test_shop_owners_customers_and_other_malls_administrators_may_not_open_the_mall_screens(): void
    {
        $otherMall = Mall::factory()->create();
        $foreignAdmin = $this->userWith(fn (User $user) => app(MembershipService::class)->grantMallRole($user, $otherMall, RoleCode::MallAdmin));

        foreach ([$this->owner, $this->customer, $foreignAdmin] as $user) {
            foreach (['index', 'export', 'forecast', 'metrics', 'reports.index'] as $name) {
                $this->actingAs($user)->get(route('mall-admin.analytics.'.$name, $this->mall))->assertForbidden();
            }
        }
    }

    public function test_bad_date_filters_are_refused_with_a_message(): void
    {
        $this->actingAs($this->owner)->from(route('workspace.insights.index', $this->shop))
            ->get(route('workspace.insights.index', [$this->shop, 'from' => '2026-03-10', 'to' => '2026-03-01']))
            ->assertRedirect(route('workspace.insights.index', $this->shop))->assertSessionHasErrors('business');

        $this->actingAs($this->owner)->from(route('workspace.insights.index', $this->shop))
            ->get(route('workspace.insights.index', [$this->shop, 'from' => '2024-01-01', 'to' => '2026-03-01']))
            ->assertSessionHasErrors('business');

        $this->actingAs($this->owner)->get(route('workspace.insights.index', [$this->shop, 'from' => 'yesterday']))->assertSessionHasErrors('from');
    }

    public function test_the_dashboard_export_is_a_csv_file_of_that_shop_only_and_is_audited(): void
    {
        $this->seedSales();
        $this->sale($this->otherShop, [[ShopProduct::factory()->create(['shop_id' => $this->otherShop->getKey()]), 7]], '2026-03-04 06:00:00');

        $response = $this->actingAs($this->owner)->get(route('workspace.insights.export', [$this->shop, 'from' => '2026-03-01', 'to' => '2026-03-10']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="shop-dashboard-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sales_revenue,"Recorded sales revenue",450.00,BDT', $response->getContent());
        $this->assertStringNotContainsString('Book Nook', $response->getContent());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'report.exported')->count());
    }

    public function test_the_mall_export_holds_every_shop_of_that_mall(): void
    {
        $this->seedSales();
        $response = $this->actingAs($this->admin)->get(route('mall-admin.analytics.export', [$this->mall, 'from' => '2026-03-01', 'to' => '2026-03-10']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Cafe Brew', $response->getContent());
        $this->assertStringContainsString('Book Nook', $response->getContent());
        $this->assertStringContainsString('"Navigation requests"', $response->getContent());
    }

    public function test_the_metric_catalogue_is_scoped_to_the_reader(): void
    {
        $shopPage = $this->actingAs($this->owner)->get(route('workspace.insights.metrics', $this->shop))->assertOk();
        $shopPage->assertSee('sales_revenue')->assertSee('stock_out_listings')->assertSee('shop_page_views')->assertSee('Formula:');
        $shopPage->assertDontSee('complaints_filed')->assertDontSee('navigation_requests')->assertDontSee('search_total');

        $mallPage = $this->actingAs($this->admin)->get(route('mall-admin.analytics.metrics', $this->mall))->assertOk();
        $mallPage->assertSee('complaints_filed')->assertSee('navigation_requests')->assertSee('Not available yet')->assertSee('search_total');
        $mallPage->assertDontSee('stock_out_listings');
    }

    public function test_the_forecast_page_is_labelled_an_estimate_and_explains_missing_history(): void
    {
        $this->actingAs($this->owner)->get(route('workspace.insights.forecast', $this->shop))
            ->assertOk()->assertSee('Estimate')->assertSee('at least 56 days')->assertSee('Limits you should know');

        $this->actingAs($this->admin)->get(route('mall-admin.analytics.forecast', $this->mall))->assertOk()->assertSee('Estimate');
    }

    public function test_an_owner_generates_reads_and_downloads_a_monthly_report(): void
    {
        $this->seedSales();

        $response = $this->actingAs($this->owner)->post(route('workspace.insights.reports.store', $this->shop), ['month' => '2026-03']);
        $report = MonthlyReport::query()->firstOrFail();

        $response->assertRedirect(route('workspace.insights.reports.show', [$this->shop, $report]));
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.index', $this->shop))->assertOk()->assertSee('March 2026');
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.show', [$this->shop, $report]))
            ->assertOk()->assertSee('Monthly report: March 2026')->assertSee('This month is not over')->assertSee('Cafe Brew recorded 2 sales worth BDT 450.00')->assertSee('Print or save as PDF');

        $csv = $this->actingAs($this->owner)->get(route('workspace.insights.reports.csv', [$this->shop, $report]))->assertOk();
        $this->assertStringContainsString('sales_revenue,"Recorded sales revenue",450.00,BDT,300.00', $csv->getContent(), 'March figure, then February for comparison');
        $this->assertStringContainsString('The month is not over', $csv->getContent());
    }

    public function test_a_bad_month_is_refused_with_a_message(): void
    {
        $this->actingAs($this->owner)->from(route('workspace.insights.reports.index', $this->shop))
            ->post(route('workspace.insights.reports.store', $this->shop), ['month' => '2027-01'])
            ->assertRedirect(route('workspace.insights.reports.index', $this->shop))->assertSessionHasErrors('business');

        $this->actingAs($this->owner)->post(route('workspace.insights.reports.store', $this->shop), [])->assertSessionHasErrors('month');
        $this->assertSame(0, MonthlyReport::query()->count());
    }

    public function test_a_report_is_only_reachable_through_the_shop_or_mall_it_belongs_to(): void
    {
        $this->seedSales();
        $service = app(MonthlyReportService::class);
        $mine = $service->generateForShop($this->owner, $this->shop, '2026-03');
        $theirs = $service->generateForShop($this->otherOwner, $this->otherShop, '2026-03');
        $mallReport = $service->generateForMall($this->admin, $this->mall, '2026-03');

        // A foreign report id through my own shop, in every place a report can be read.
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.show', [$this->shop, $theirs]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.csv', [$this->shop, $theirs]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.show', [$this->shop, $mallReport]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.reports.show', [$this->mall, $mine]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.reports.csv', [$this->mall, $theirs]))->assertNotFound();

        // And through the other shop's URL, which I may not enter at all.
        $this->actingAs($this->owner)->get(route('workspace.insights.reports.show', [$this->otherShop, $theirs]))->assertForbidden();
        $this->actingAs($this->otherOwner)->get(route('workspace.insights.reports.show', [$this->shop, $mine]))->assertForbidden();
        $this->actingAs($this->owner)->get(route('mall-admin.analytics.reports.show', [$this->mall, $mallReport]))->assertForbidden();
    }

    public function test_the_mall_report_pages_work_for_the_administrator(): void
    {
        $this->seedSales();

        $this->actingAs($this->admin)->post(route('mall-admin.analytics.reports.store', $this->mall), ['month' => '2026-02'])->assertRedirect();
        $report = MonthlyReport::query()->where('scope', 'mall')->firstOrFail();

        $this->actingAs($this->admin)->get(route('mall-admin.analytics.reports.index', $this->mall))->assertOk()->assertSee('February 2026');
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.reports.show', [$this->mall, $report]))->assertOk()->assertSee($this->mall->name)->assertSee('Shops');
        $this->actingAs($this->admin)->get(route('mall-admin.analytics.reports.csv', [$this->mall, $report]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_hubs_link_to_the_new_screens(): void
    {
        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))->assertOk()->assertSee(route('mall-admin.analytics.index', $this->mall), false)->assertSee(route('mall-admin.analytics.reports.index', $this->mall), false);
        $this->actingAs($this->owner)->get(route('workspace.dashboard', $this->shop))->assertOk()->assertSee(route('workspace.insights.index', $this->shop), false);
    }
}
