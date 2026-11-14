<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Services\Reporting\ForecastService;
use App\Modules\MallShopDirectory\Models\Shop;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseFactory;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The forecast prototype on a made-up but regular history: a weekly pattern (busy Saturdays) with a small
 * upward trend. The clock stands at 2026-03-15; the last complete day is 2026-03-14.
 */
class ForecastTest extends ReportTestCase
{
    private const WEEKDAY = [1.0, 1.1, 1.2, 1.4, 1.8, 2.2, 1.6];

    /**
     * Records one sale per day for the last $days complete days (ending 2026-03-14) with a revenue taken from
     * the weekly pattern, a trend and a small repeatable wobble.
     */
    private function history(Shop $shop, int $days, string $receiptPrefix = 'RCPT'): void
    {
        $first = CarbonImmutable::parse('2026-03-14 06:00:00', 'UTC')->subDays($days - 1);

        for ($i = 0; $i < $days; $i++) {
            $day = $first->addDays($i);
            $revenue = round(1000 * self::WEEKDAY[$day->dayOfWeekIso - 1] + 5 * $i + (($i * 37) % 11) * 10, 2);

            PurchaseFactory::new()->state([
                'shop_id' => $shop->getKey(), 'purchased_at' => $day->format('Y-m-d H:i:s'), 'recorded_at' => $day->format('Y-m-d H:i:s'),
                'total_amount' => $revenue, 'external_receipt_ref' => $receiptPrefix.'-'.$shop->getKey().'-'.$i,
            ])->create();
        }
    }

    private function forecast(): array
    {
        return app(ForecastService::class)->forShop($this->owner, $this->shop);
    }

    public function test_a_regular_history_gives_a_complete_labelled_forecast(): void
    {
        $this->history($this->shop, 100);
        $f = $this->forecast();

        $this->assertSame('ok', $f['status']);
        $this->assertSame(100, $f['history']['days']);
        $this->assertSame(100, $f['history']['sales_days']);
        $this->assertSame('2026-03-14', $f['training_cutoff']);
        $this->assertCount(14, $f['forecast']);
        $this->assertSame('2026-03-15', $f['forecast'][0]['day']);
        $this->assertSame('2026-03-28', $f['forecast'][13]['day']);
        $this->assertCount(4, $f['models']);
        $this->assertCount(1, array_filter($f['models'], fn (array $m): bool => $m['selected']));
        $this->assertCount(2, array_filter($f['models'], fn (array $m): bool => $m['baseline']));
        $this->assertNotSame('', $f['verdict']);
        $this->assertNotEmpty($f['limitations']);

        foreach ($f['forecast'] as $row) {
            $this->assertGreaterThanOrEqual(0.0, $row['low']);
            $this->assertLessThanOrEqual($row['point'], $row['low']);
            $this->assertGreaterThanOrEqual($row['point'], $row['high']);
        }

        // 2026-03-21 is a Saturday (busiest), 2026-03-17 a Tuesday.
        $byDay = array_column($f['forecast'], 'point', 'day');
        $this->assertGreaterThan($byDay['2026-03-17'] * 1.5, $byDay['2026-03-21']);
    }

    public function test_regression_reports_the_trend_and_weekday_effects_with_tests(): void
    {
        $this->history($this->shop, 100);
        $r = $this->forecast()['regression'];

        $this->assertSame(100, $r['observations']);
        $this->assertEqualsWithDelta(35.0, $r['trend_per_week'], 8.0, 'the history rises by 5 per day, 35 per week');
        $this->assertLessThan(0.001, $r['trend_p']);
        $this->assertGreaterThan(0.9, $r['r_squared']);
        $this->assertCount(6, $r['weekday_effects']);
        $this->assertEqualsWithDelta(1200.0, array_column($r['weekday_effects'], 'effect', 'weekday')['Saturday'], 60.0, 'Saturday is about 1,200 above Monday');
    }

    public function test_the_same_history_always_gives_the_same_forecast_and_run_id(): void
    {
        $this->history($this->shop, 100);

        $first = $this->forecast();
        $second = $this->forecast();

        $this->assertSame($first['run_id'], $second['run_id']);
        $this->assertSame($first['forecast'], $second['forecast']);
        $this->assertSame($first['models'], $second['models']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $first['run_id']);
    }

    public function test_the_held_out_days_cannot_influence_the_choice_of_method(): void
    {
        $this->history($this->shop, 100);
        $before = $this->forecast();

        // A one-off sale inside the last 14 days (the hold-out) changes the hold-out errors only.
        PurchaseFactory::new()->state(['shop_id' => $this->shop->getKey(), 'purchased_at' => '2026-03-10 07:00:00', 'recorded_at' => '2026-03-10 07:00:00', 'total_amount' => 9000, 'external_receipt_ref' => 'RCPT-ONEOFF'])->create();
        $after = $this->forecast();

        $this->assertNotSame($before['run_id'], $after['run_id']);
        $this->assertSame(array_column($before['models'], 'cv_mae'), array_column($after['models'], 'cv_mae'), 'cross-validation only sees the days before the hold-out');
        $this->assertSame($before['ridge_lambda'], $after['ridge_lambda']);
        $this->assertSame($before['selected'], $after['selected']);
        $this->assertNotSame(array_column(array_column($before['models'], 'holdout'), 'mae'), array_column(array_column($after['models'], 'holdout'), 'mae'));
    }

    public function test_too_little_history_gives_no_forecast_and_says_why(): void
    {
        $this->history($this->shop, 30);
        $f = $this->forecast();

        $this->assertSame('insufficient', $f['status']);
        $this->assertStringContainsString('at least 56 days', $f['reason']);
        $this->assertArrayNotHasKey('forecast', $f);
        $this->assertSame(30, $f['history']['days']);
    }

    public function test_days_before_the_first_sale_are_not_part_of_the_history(): void
    {
        $this->history($this->shop, 70);

        $this->assertSame(70, $this->forecast()['history']['days'], 'the 180-day window starts at the first sale, not before it');
    }

    public function test_simulated_records_are_counted_and_mentioned(): void
    {
        $this->history($this->shop, 60, 'SIM');
        $f = $this->forecast();

        $this->assertSame(['simulated' => 60, 'total' => 60], $f['provenance']);
        $this->assertStringContainsString('simulated demonstration records', implode(' ', $f['limitations']));
    }

    public function test_the_mall_forecast_covers_every_shop_and_nothing_else(): void
    {
        $this->history($this->shop, 70);
        $this->history($this->otherShop, 70);
        $f = app(ForecastService::class)->forMall($this->admin, $this->mall);

        $own = app(ForecastService::class)->forShop($this->owner, $this->shop);
        $other = app(ForecastService::class)->forShop($this->otherOwner, $this->otherShop);

        $this->assertSame('ok', $f['status']);
        $this->assertEqualsWithDelta($own['history']['revenue'] + $other['history']['revenue'], $f['history']['revenue'], 0.01);
        $this->assertSame('mall', $f['scope']);
    }

    public function test_only_the_owner_or_the_mall_administrator_may_forecast(): void
    {
        $this->history($this->shop, 60);

        $this->expectException(AuthorizationException::class);
        app(ForecastService::class)->forShop($this->otherOwner, $this->shop);
    }

    public function test_a_mall_forecast_is_refused_to_a_shop_owner(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ForecastService::class)->forMall($this->owner, $this->mall);
    }
}
