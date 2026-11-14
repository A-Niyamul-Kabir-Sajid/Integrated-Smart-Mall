<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Models\ActivitySummary;
use App\Modules\AnalyticsReports\Models\SearchEvent;
use App\Modules\AnalyticsReports\Models\ViewEvent;
use App\Modules\AnalyticsReports\Services\ActivityRollupService;
use App\Modules\AnalyticsReports\Services\DemoTrafficService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoMallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTrafficTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private DemoTrafficService $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);

        foreach (range(1, 3) as $i) {
            $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
            ShopProduct::factory()->create(['shop_id' => $shop->getKey()]);
        }

        $this->demo = app(DemoTrafficService::class);
    }

    public function test_every_demonstration_row_carries_the_demo_label_and_the_summaries_exist(): void
    {
        $written = $this->demo->seed($this->mall, 10);

        $this->assertGreaterThan(100, $written['search']);
        $this->assertGreaterThan(100, $written['view']);
        $this->assertSame($written['search'], SearchEvent::query()->where('dataset_label', DatasetLabel::Demo)->count());
        $this->assertSame(0, SearchEvent::query()->where('dataset_label', DatasetLabel::Real)->count());
        $this->assertSame($written['view'], ViewEvent::query()->where('dataset_label', DatasetLabel::Demo)->count());
        $this->assertSame(0, ViewEvent::query()->where('dataset_label', DatasetLabel::Real)->count());
        $this->assertGreaterThan(0, ActivitySummary::query()->count());
        $this->assertSame(0, ActivitySummary::query()->where('dataset_label', DatasetLabel::Real)->count());
    }

    public function test_the_same_seed_gives_the_same_numbers_and_running_again_replaces_instead_of_adding(): void
    {
        $first = $this->demo->seed($this->mall, 10);
        $firstSummary = ActivitySummary::query()->orderBy('activity_daily_summary_id')->get()->map(fn ($row) => [$row->day->format('Y-m-d'), ...$row->only(['metric_code', 'kind', 'subject_key', 'value', 'secondary'])])->all();

        $second = $this->demo->seed($this->mall, 10);
        $secondSummary = ActivitySummary::query()->orderBy('activity_daily_summary_id')->get()->map(fn ($row) => [$row->day->format('Y-m-d'), ...$row->only(['metric_code', 'kind', 'subject_key', 'value', 'secondary'])])->all();

        $this->assertSame($first, $second);
        $this->assertSame($first['search'], SearchEvent::query()->count());
        $this->assertSame($firstSummary, $secondSummary);
    }

    public function test_real_traffic_is_never_touched_and_never_shown_as_demo(): void
    {
        $shop = Shop::query()->first();
        ViewEvent::query()->create(['mall_id' => $this->mall->getKey(), 'kind' => 'shop', 'shop_id' => $shop->getKey(), 'visitor_key' => hash('sha256', 'real'), 'dataset_label' => DatasetLabel::Real, 'occurred_at' => now()]);
        SearchEvent::query()->create(['mall_id' => $this->mall->getKey(), 'kind' => 'shop', 'term' => 'realterm', 'term_masked' => false, 'result_count' => 1, 'signed_in' => false, 'dataset_label' => DatasetLabel::Real, 'occurred_at' => now()]);

        $this->demo->seed($this->mall, 5);
        $this->demo->clear($this->mall);

        $this->assertSame(1, ViewEvent::query()->count());
        $this->assertSame(['realterm'], SearchEvent::query()->pluck('term')->all());
        $this->assertSame(0, ViewEvent::query()->where('dataset_label', DatasetLabel::Demo)->count());
    }

    public function test_demonstration_traffic_never_reaches_the_real_dashboards(): void
    {
        $admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($admin, $this->mall, RoleCode::MallAdmin);
        $this->demo->seed($this->mall, 10);

        $real = $this->actingAs($admin)->get(route('mall-admin.analytics.search', $this->mall));
        $real->assertOk()->assertSee('No searches were recorded in this period.');
        $this->assertSame(0, $real->viewData('report')['searches']);

        $demo = $this->actingAs($admin)->get(route('mall-admin.analytics.search', [$this->mall, 'dataset' => 'demo']));
        $this->assertGreaterThan(0, $demo->viewData('report')['searches']);
        $this->assertContains('umbrella', $demo->viewData('report')['unmet']->pluck('term')->all(), 'The demo data contains terms that are never found.');
        $this->assertNotContains('coffee', $demo->viewData('report')['unmet']->pluck('term')->all());
    }

    public function test_the_demo_summaries_reconcile_with_the_demo_events(): void
    {
        $this->demo->seed($this->mall, 7);

        $this->assertSame(SearchEvent::query()->count(), (int) ActivitySummary::query()->where('metric_code', 'search_total')->sum('value'));
        $this->assertSame(SearchEvent::query()->where('result_count', 0)->count(), (int) ActivitySummary::query()->where('metric_code', 'search_total')->sum('secondary'));
        $this->assertSame(ViewEvent::query()->where('kind', 'shop')->count(), (int) ActivitySummary::query()->where('metric_code', 'shop_page_views')->sum('value'));
        $this->assertSame(ViewEvent::query()->where('kind', 'product')->count(), (int) ActivitySummary::query()->where('metric_code', 'product_page_views')->sum('value'));

        $before = ActivitySummary::query()->count();
        app(ActivityRollupService::class)->rollupRange($this->mall, CarbonImmutable::now('Asia/Dhaka')->subDays(6), CarbonImmutable::now('Asia/Dhaka'));
        $this->assertSame($before, ActivitySummary::query()->count(), 'Rolling up again changes nothing.');
    }

    public function test_the_database_seeder_adds_demonstration_traffic_for_the_demo_mall(): void
    {
        $this->seed(DemoMallSeeder::class);
        $this->seed(DemoActivitySeeder::class);

        $demoMall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->firstOrFail();

        $this->assertGreaterThan(0, SearchEvent::query()->where('mall_id', $demoMall->getKey())->count());
        $this->assertSame(0, SearchEvent::query()->where('dataset_label', DatasetLabel::Real)->count());
        $this->assertSame(0, SearchEvent::query()->where('mall_id', $this->mall->getKey())->count(), 'Only the demo mall gets demonstration traffic.');
    }
}
