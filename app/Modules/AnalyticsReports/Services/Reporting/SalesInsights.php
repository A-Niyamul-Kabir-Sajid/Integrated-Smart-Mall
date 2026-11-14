<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AnalyticsReports\Algorithms\ParetoClassifier;
use App\Modules\AnalyticsReports\Algorithms\RobustStats;
use Carbon\CarbonImmutable;

/**
 * Analyses of sales figures that both the shop and the mall dashboards use: unusual days, and the
 * product table with its ABC and XYZ classes, stock cover and slow movers.
 */
class SalesInsights
{
    private const MIN_DAYS = 28;

    private const MIN_SALES_DAYS = 10;

    private const THRESHOLD = 3.5;

    /**
     * Days whose revenue is far from what the same weekday usually brings. The expected value of a day is
     * the median revenue of its weekday in the period, so a busy Saturday is not unusual by itself.
     *
     * @param  array<string, float>  $daily  revenue per day, every day present
     * @param  string|null  $today  the mall's current day (Y-m-d); that day and later ones are not finished and are left out
     * @return array{available: bool, reason: ?string, days: list<array{day: string, revenue: float, expected: float, z: float, direction: string}>}
     */
    public function anomalies(array $daily, ?string $today = null): array
    {
        if ($today !== null) {
            $daily = array_filter($daily, fn (string $day): bool => $day < $today, ARRAY_FILTER_USE_KEY);
        }

        $withSales = count(array_filter($daily, fn (float $revenue): bool => $revenue > 0));

        if (count($daily) < self::MIN_DAYS || $withSales < self::MIN_SALES_DAYS) {
            return ['available' => false, 'reason' => __('Needs at least :days days with sales on :sales of them to say what is unusual.', ['days' => self::MIN_DAYS, 'sales' => self::MIN_SALES_DAYS]), 'days' => []];
        }

        $byWeekday = [];

        foreach ($daily as $day => $revenue) {
            $byWeekday[CarbonImmutable::parse($day)->dayOfWeekIso][] = $revenue;
        }

        $expected = array_map(fn (array $values): float => RobustStats::median($values), $byWeekday);
        $gaps = [];

        foreach ($daily as $day => $revenue) {
            $gaps[$day] = $revenue - $expected[CarbonImmutable::parse($day)->dayOfWeekIso];
        }

        $scores = array_combine(array_keys($gaps), RobustStats::modifiedZScores(array_values($gaps)));
        $flagged = [];

        foreach ($scores as $day => $z) {
            if (abs($z) >= self::THRESHOLD) {
                $flagged[] = ['day' => $day, 'revenue' => $daily[$day], 'expected' => round($expected[CarbonImmutable::parse($day)->dayOfWeekIso], 2), 'z' => round($z, 1), 'direction' => $z > 0 ? 'high' : 'low'];
            }
        }

        return ['available' => true, 'reason' => null, 'days' => $flagged];
    }

    /**
     * One row per listing that sold or is active, ranked by revenue.
     *
     * @param  array<string, array{revenue: float, units: int, weekly: list<float>}>  $sales
     * @param  array<string, string>  $labels  listing id => name, for the listings to show
     * @param  array<string, int>  $stock  listing id => units on hand, for the listings that track stock
     * @return list<array{listing: string, label: string, revenue: float, units: int, share: float, abc: string, xyz: ?string, on_hand: ?int, days_of_cover: ?int, slow_moving: bool}>
     */
    public function productRows(array $sales, array $labels, array $stock, int $dayCount): array
    {
        $keys = array_values(array_unique([...array_keys($sales), ...array_keys($labels)]));
        $revenue = [];

        foreach ($keys as $key) {
            $revenue[$key] = (float) ($sales[$key]['revenue'] ?? 0.0);
        }

        $classes = ParetoClassifier::abc($revenue);
        $total = array_sum($revenue);
        $rows = [];

        foreach ($keys as $key) {
            $units = (int) ($sales[$key]['units'] ?? 0);
            $weekly = $sales[$key]['weekly'] ?? [];
            $weeks = intdiv($dayCount, 7);
            $onHand = $stock[$key] ?? null;
            $perDay = $units / max(1, $dayCount);

            $rows[] = [
                'listing' => $key,
                'label' => $labels[$key] ?? __('Removed listing'),
                'revenue' => round($revenue[$key], 2),
                'units' => $units,
                'share' => $total > 0 ? round($revenue[$key] / $total * 100, 1) : 0.0,
                'abc' => $classes[$key],
                'xyz' => $weeks >= 2 ? ParetoClassifier::xyz($weekly === [] ? array_fill(0, $weeks, 0.0) : $weekly) : null,
                'on_hand' => $onHand,
                'days_of_cover' => $onHand !== null && $perDay > 0 ? (int) round($onHand / $perDay) : null,
                'slow_moving' => $onHand !== null && $onHand > 0 && $units === 0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['revenue'], $a['label']] <=> [$a['revenue'], $b['label']]);

        return $rows;
    }
}
