<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;
use App\Modules\AnalyticsReports\Models\ActivitySummary;
use App\Modules\AnalyticsReports\Models\SearchEvent;
use App\Modules\AnalyticsReports\Models\ViewEvent;
use App\Modules\AnalyticsReports\Services\ActivityRetentionService;
use App\Modules\AnalyticsReports\Services\ActivityRollupService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityRollupTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Shop $shop;

    private Product $product;

    private ActivityRollupService $rollups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $this->product = Product::factory()->create();
        $this->rollups = app(ActivityRollupService::class);
    }

    private function search(string $at, ?string $term, int $results, string $kind = 'shop', array $extra = []): SearchEvent
    {
        return SearchEvent::query()->create([
            'mall_id' => $this->mall->getKey(), 'kind' => $kind, 'term' => $term, 'term_masked' => $term === null,
            'category' => null, 'floor' => null, 'result_count' => $results, 'signed_in' => false,
            'dataset_label' => DatasetLabel::Real, 'occurred_at' => $at, ...$extra,
        ]);
    }

    private function pageView(string $at, string $kind, string $visitor, ?DatasetLabel $label = null): ViewEvent
    {
        return ViewEvent::query()->create([
            'mall_id' => $this->mall->getKey(), 'kind' => $kind,
            'shop_id' => $kind === 'shop' ? $this->shop->getKey() : null,
            'product_id' => $kind === 'product' ? $this->product->getKey() : null,
            'visitor_key' => hash('sha256', $visitor), 'dataset_label' => $label ?? DatasetLabel::Real, 'occurred_at' => $at,
        ]);
    }

    private function total(SummaryMetric $metric, string $subject = '', string $label = 'real', string $day = '2026-03-10', string $kind = ''): ?ActivitySummary
    {
        return ActivitySummary::query()->where('metric_code', $metric->value)->where('subject_key', $subject)->where('dataset_label', $label)->where('day', $day)->where('kind', $kind)->first();
    }

    private function day(string $date = '2026-03-10'): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Dhaka');
    }

    public function test_searches_are_summarised_per_term_with_zero_result_counts_and_masked_searches_in_the_total(): void
    {
        $this->search('2026-03-10 05:00:00', 'coffee', 3);
        $this->search('2026-03-10 06:00:00', 'coffee', 2);
        $this->search('2026-03-10 07:00:00', 'umbrella', 0);
        $this->search('2026-03-10 08:00:00', 'umbrella', 0);
        $this->search('2026-03-10 09:00:00', 'umbrella', 0, 'product');
        $this->search('2026-03-10 10:00:00', null, 0);

        $this->rollups->rollupDay($this->mall, $this->day());

        $this->assertSame(5, $this->total(SummaryMetric::SearchTerm, 'umbrella', kind: 'shop')?->value + $this->total(SummaryMetric::SearchTerm, 'coffee', kind: 'shop')->value + $this->total(SummaryMetric::SearchTerm, 'umbrella', kind: 'product')->value);
        $this->assertSame(2, $this->total(SummaryMetric::SearchTerm, 'coffee', kind: 'shop')->value);
        $this->assertSame(2, $this->total(SummaryMetric::SearchTermZeroResult, 'umbrella', kind: 'shop')->value);
        $this->assertSame(1, $this->total(SummaryMetric::SearchTermZeroResult, 'umbrella', kind: 'product')->value);
        $this->assertNull($this->total(SummaryMetric::SearchTermZeroResult, 'coffee', kind: 'shop'));

        $shopTotal = $this->total(SummaryMetric::SearchTotal, kind: 'shop');
        $this->assertSame(5, $shopTotal->value, 'The masked search counts in the total.');
        $this->assertSame(3, $shopTotal->secondary, 'Three shop searches found nothing, including the masked one.');
        $this->assertSame(SummaryMetric::FORMULA_VERSION, $shopTotal->formula_version);
        $this->assertSame(0, ActivitySummary::query()->where('subject_key', '')->where('metric_code', SummaryMetric::SearchTerm->value)->count(), 'A masked term never becomes a subject.');
    }

    public function test_filters_views_and_unique_visitors_are_summarised(): void
    {
        $this->search('2026-03-10 05:00:00', 'coffee', 1, extra: ['category' => 'food', 'floor' => 'G']);
        $this->search('2026-03-10 05:30:00', 'tea', 1, extra: ['category' => 'food']);
        $this->pageView('2026-03-10 05:00:00', 'shop', 'a');
        $this->pageView('2026-03-10 06:00:00', 'shop', 'a');
        $this->pageView('2026-03-10 07:00:00', 'shop', 'b');
        $this->pageView('2026-03-10 08:00:00', 'product', 'a');

        $this->rollups->rollupDay($this->mall, $this->day());

        $this->assertSame(2, $this->total(SummaryMetric::SearchCategory, 'food', kind: 'shop')->value);
        $this->assertSame(1, $this->total(SummaryMetric::SearchFloor, 'G', kind: 'shop')->value);

        $shopViews = $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), kind: 'shop');
        $this->assertSame(3, $shopViews->value);
        $this->assertSame(2, $shopViews->secondary, 'Visitor a came twice and counts once.');
        $this->assertSame($this->shop->getKey(), $shopViews->shop_id);

        $productViews = $this->total(SummaryMetric::ProductPageViews, $this->product->getKey(), kind: 'product');
        $this->assertSame(1, $productViews->value);
        $this->assertNull($productViews->shop_id);
    }

    public function test_days_follow_the_mall_time_zone(): void
    {
        // Dhaka is UTC+6: 17:59 UTC is 23:59 on the 10th, 18:00 UTC is 00:00 on the 11th.
        $this->pageView('2026-03-10 17:59:59', 'shop', 'a');
        $this->pageView('2026-03-10 18:00:00', 'shop', 'b');
        $this->pageView('2026-03-09 18:00:00', 'shop', 'c');
        $this->pageView('2026-03-09 17:59:59', 'shop', 'd');

        $this->rollups->rollupRange($this->mall, $this->day('2026-03-09'), $this->day('2026-03-11'));

        $key = fn (string $day): int => (int) $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), day: $day, kind: 'shop')?->value;

        $this->assertSame(1, $key('2026-03-09'), 'Only d falls on the 9th in Dhaka.');
        $this->assertSame(2, $key('2026-03-10'), 'c at 00:00 and a at 23:59 are both the 10th.');
        $this->assertSame(1, $key('2026-03-11'));
    }

    public function test_real_and_demonstration_rows_are_kept_apart(): void
    {
        $this->pageView('2026-03-10 05:00:00', 'shop', 'a');
        $this->pageView('2026-03-10 05:00:00', 'shop', 'b', DatasetLabel::Demo);
        $this->pageView('2026-03-10 06:00:00', 'shop', 'c', DatasetLabel::Demo);

        $this->rollups->rollupDay($this->mall, $this->day());

        $this->assertSame(1, $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), 'real', kind: 'shop')->value);
        $this->assertSame(2, $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), 'demo', kind: 'shop')->value);
    }

    public function test_running_a_day_twice_gives_identical_rows_and_late_events_update_it_with_a_new_cutoff(): void
    {
        $this->pageView('2026-03-10 05:00:00', 'shop', 'a');
        $this->search('2026-03-10 05:00:00', 'coffee', 1);

        $this->rollups->rollupDay($this->mall, $this->day());
        $first = ActivitySummary::query()->orderBy('activity_daily_summary_id')->get()->map(fn ($row) => $row->only(['metric_code', 'kind', 'subject_key', 'value', 'secondary']))->all();
        $firstCutoff = ActivitySummary::query()->first()->source_cutoff_at;

        $this->travel(5)->minutes();
        $this->rollups->rollupDay($this->mall, $this->day());
        $second = ActivitySummary::query()->orderBy('activity_daily_summary_id')->get()->map(fn ($row) => $row->only(['metric_code', 'kind', 'subject_key', 'value', 'secondary']))->all();

        $this->assertSame($first, $second);
        $this->assertTrue(ActivitySummary::query()->first()->source_cutoff_at->gt($firstCutoff), 'The cut-off records when the events were read.');

        $this->pageView('2026-03-10 06:00:00', 'shop', 'b');
        $this->rollups->rollupDay($this->mall, $this->day());

        $this->assertSame(2, $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), kind: 'shop')->value);
        $this->assertSame(1, ActivitySummary::query()->where('metric_code', SummaryMetric::ShopPageViews->value)->count(), 'No duplicate row.');
    }

    public function test_another_malls_events_never_enter_the_summary(): void
    {
        $other = Mall::factory()->create();
        SearchEvent::query()->create(['mall_id' => $other->getKey(), 'kind' => 'shop', 'term' => 'secret', 'term_masked' => false, 'result_count' => 0, 'signed_in' => false, 'dataset_label' => DatasetLabel::Real, 'occurred_at' => '2026-03-10 05:00:00']);
        $this->search('2026-03-10 05:00:00', 'coffee', 1);

        $this->rollups->rollupDay($this->mall, $this->day());

        $this->assertSame(0, ActivitySummary::query()->where('subject_key', 'secret')->count());
        $this->assertSame(1, ActivitySummary::query()->where('metric_code', SummaryMetric::SearchTotal->value)->sum('value'));
    }

    public function test_the_rollup_command_recomputes_the_latest_days_and_one_chosen_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC'));
        $this->search('2026-03-10 05:00:00', 'coffee', 1);
        $this->search('2026-03-09 05:00:00', 'tea', 1);
        $this->search('2026-03-05 05:00:00', 'old', 1);

        $this->artisan('analytics:rollup', ['--days' => 2])->assertSuccessful();

        $this->assertSame(1, $this->total(SummaryMetric::SearchTerm, 'coffee', kind: 'shop')->value);
        $this->assertSame(1, $this->total(SummaryMetric::SearchTerm, 'tea', day: '2026-03-09', kind: 'shop')->value);
        $this->assertNull($this->total(SummaryMetric::SearchTerm, 'old', day: '2026-03-05', kind: 'shop'));

        $this->artisan('analytics:rollup', ['--date' => '2026-03-05'])->assertSuccessful();
        $this->assertSame(1, $this->total(SummaryMetric::SearchTerm, 'old', day: '2026-03-05', kind: 'shop')->value);
    }

    public function test_expired_events_are_summarised_then_deleted_and_summaries_and_recent_events_stay(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-30 12:00:00', 'UTC'));
        $old = CarbonImmutable::now()->subDays(100)->format('Y-m-d 05:00:00');
        $recent = CarbonImmutable::now()->subDays(10)->format('Y-m-d 05:00:00');
        $this->search($old, 'old term', 0);
        $this->pageView($old, 'shop', 'a');
        $this->search($recent, 'recent term', 1);
        $this->pageView($recent, 'shop', 'b');
        $this->assertSame(0, ActivitySummary::query()->count(), 'Nothing was rolled up yet.');

        $deleted = app(ActivityRetentionService::class)->purge();

        $this->assertSame(['search' => 1, 'view' => 1], $deleted);
        $this->assertSame(['recent term'], SearchEvent::query()->pluck('term')->all());
        $this->assertSame(1, ViewEvent::query()->count());
        $oldDay = CarbonImmutable::parse($old, 'UTC')->setTimezone('Asia/Dhaka')->format('Y-m-d');
        $this->assertSame(1, $this->total(SummaryMetric::SearchTerm, 'old term', day: $oldDay, kind: 'shop')?->value, 'The expired day was summarised before its events were deleted.');
        $this->assertSame(1, $this->total(SummaryMetric::ShopPageViews, $this->shop->getKey(), day: $oldDay, kind: 'shop')?->value);

        $this->assertSame(['search' => 0, 'view' => 0], app(ActivityRetentionService::class)->purge(), 'A second run deletes nothing.');
        $this->artisan('analytics:purge-events')->assertSuccessful();
    }

    public function test_every_metric_in_the_catalogue_is_defined(): void
    {
        foreach (SummaryMetric::cases() as $metric) {
            $this->assertNotSame('', $metric->label());
            $this->assertGreaterThan(30, strlen($metric->formula()), "{$metric->value} needs a formula.");
            $this->assertNotSame('', $metric->visibility());
            $this->assertContains($metric->sourceTable(), ['activity_search_events', 'activity_view_events']);
            $this->assertLessThanOrEqual(40, strlen($metric->value));
        }

        $this->assertSame(count(SummaryMetric::cases()), count(array_unique(array_map(fn (SummaryMetric $metric): string => $metric->value, SummaryMetric::cases()))));
    }
}
