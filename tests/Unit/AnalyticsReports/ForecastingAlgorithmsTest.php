<?php

namespace Tests\Unit\AnalyticsReports;

use App\Modules\AnalyticsReports\Algorithms\ForecastMetrics;
use App\Modules\AnalyticsReports\Algorithms\HoltWinters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ForecastingAlgorithmsTest extends TestCase
{
    public function test_error_measures_match_hand_calculation(): void
    {
        $actual = [3.0, -0.5, 2.0, 7.0];
        $predicted = [2.5, 0.0, 2.0, 8.0];

        $this->assertEqualsWithDelta(0.5, ForecastMetrics::mae($actual, $predicted), 1e-12);
        $this->assertEqualsWithDelta(sqrt(0.375), ForecastMetrics::rmse($actual, $predicted), 1e-12);
        $this->assertEqualsWithDelta(0.25, ForecastMetrics::bias($actual, $predicted), 1e-12);
        $this->assertEqualsWithDelta(57.88, ForecastMetrics::smape($actual, $predicted), 0.01);
        // Training 1..8 changes by 1 per step, so the naive scale is 1 and MASE equals the MAE.
        $this->assertEqualsWithDelta(0.5, ForecastMetrics::mase($actual, $predicted, [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0], 1), 1e-12);
        $this->assertNull(ForecastMetrics::mase($actual, $predicted, [5.0, 5.0, 5.0], 1));
    }

    public function test_smape_counts_two_zeros_as_a_perfect_day(): void
    {
        $this->assertSame(0.0, ForecastMetrics::smape([0.0, 0.0], [0.0, 0.0]));
    }

    public function test_holt_winters_reproduces_a_pure_weekly_pattern(): void
    {
        $week = [10.0, 12.0, 14.0, 20.0, 30.0, 25.0, 15.0];
        $series = array_merge(...array_fill(0, 8, $week));

        $forecast = HoltWinters::fit($series, 7)->forecast(7);

        foreach ($week as $day => $expected) {
            $this->assertEqualsWithDelta($expected, $forecast[$day], 0.5);
        }
    }

    public function test_holt_winters_follows_a_rising_level_with_the_weekly_shape(): void
    {
        $week = [10.0, 12.0, 14.0, 20.0, 30.0, 25.0, 15.0];
        $series = [];

        for ($t = 0; $t < 70; $t++) {
            $series[] = $week[$t % 7] + 0.2 * $t;
        }

        $forecast = HoltWinters::fit($series, 7)->forecast(7);

        // The best guess for the next week sits above the last week's average and keeps the weekly shape.
        $this->assertGreaterThan(array_sum(array_slice($series, -7)) / 7, array_sum($forecast) / 7);
        $this->assertGreaterThan($forecast[0], $forecast[4]);
        $this->assertSame(7, count($forecast));
    }

    public function test_holt_winters_is_deterministic_and_needs_two_full_cycles(): void
    {
        $series = array_merge(...array_fill(0, 5, [3.0, 4.0, 5.0, 9.0, 12.0, 10.0, 6.0]));

        $this->assertSame(HoltWinters::fit($series, 7)->forecast(5), HoltWinters::fit($series, 7)->forecast(5));

        $this->expectException(InvalidArgumentException::class);
        HoltWinters::fit(array_slice($series, 0, 13), 7);
    }
}
