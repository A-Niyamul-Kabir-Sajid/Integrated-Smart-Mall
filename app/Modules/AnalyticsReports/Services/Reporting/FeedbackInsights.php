<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AnalyticsReports\Algorithms\LogOddsTerms;
use App\Modules\AnalyticsReports\Algorithms\RidgeRegression;
use Carbon\CarbonImmutable;

/**
 * Analyses of published reviews used by the shop and the mall dashboards: the rating summary, the
 * rating trend (a least-squares slope with a t-test) and the words that set poor reviews apart from good ones.
 */
class FeedbackInsights
{
    public const TREND_MIN_REVIEWS = 8;

    public const TREND_MIN_SPAN_DAYS = 28;

    public const TREND_WINDOW_DAYS = 120;

    public const TREND_SIGNIFICANCE = 0.10;

    private const TERMS_MIN_SIDE = 3;

    /**
     * "< 0.001" or "= 0.034", for text such as "(p :p)".
     */
    public static function formatP(float $p): string
    {
        return $p < 0.001 ? '< 0.001' : '= '.number_format($p, 3);
    }

    /**
     * @param  list<array{shop: string, rating: int, body: string, at: CarbonImmutable}>  $reviews
     * @return array{count: int, average: ?float, distribution: array<int, int>, terms: array{available: bool, reason: ?string, praise: list<array{term: string, count_a: int, count_b: int, z: float}>, complaints: list<array{term: string, count_a: int, count_b: int, z: float}>}}
     */
    public function summary(array $reviews): array
    {
        $distribution = array_fill_keys([5, 4, 3, 2, 1], 0);

        foreach ($reviews as $review) {
            $distribution[$review['rating']]++;
        }

        $count = count($reviews);

        return [
            'count' => $count,
            'average' => $count > 0 ? round(array_sum(array_column($reviews, 'rating')) / $count, 2) : null,
            'distribution' => $distribution,
            'terms' => $this->terms($reviews),
        ];
    }

    /**
     * @param  list<array{shop: string, rating: int, body: string, at: CarbonImmutable}>  $reviews  published reviews of the trend window, oldest first
     * @return array{available: bool, reason: ?string, reviews: int, slope_per_30_days: ?float, p_value: ?float, significant: bool, direction: ?string}
     */
    public function trend(array $reviews): array
    {
        $none = fn (string $reason): array => ['available' => false, 'reason' => $reason, 'reviews' => count($reviews), 'slope_per_30_days' => null, 'p_value' => null, 'significant' => false, 'direction' => null];

        if (count($reviews) < self::TREND_MIN_REVIEWS) {
            return $none(__('Needs at least :count published reviews in the last :days days.', ['count' => self::TREND_MIN_REVIEWS, 'days' => self::TREND_WINDOW_DAYS]));
        }

        $first = $reviews[0]['at'];
        $rows = array_map(fn (array $r): array => [($r['at']->getTimestamp() - $first->getTimestamp()) / 86400], $reviews);
        $ratings = array_map(fn (array $r): float => (float) $r['rating'], $reviews);

        if (max(array_column($rows, 0)) < self::TREND_MIN_SPAN_DAYS) {
            return $none(__('The reviews cover less than :days days, too short to see a trend.', ['days' => self::TREND_MIN_SPAN_DAYS]));
        }

        $model = RidgeRegression::fit($rows, $ratings);
        $slope = $model->coefficients()['coefficients'][0] * 30;
        $p = $model->pValues()[0] ?? ($slope === 0.0 ? 1.0 : 0.0);

        return [
            'available' => true,
            'reason' => null,
            'reviews' => count($reviews),
            'slope_per_30_days' => round($slope, 2),
            'p_value' => round($p, 4),
            'significant' => $p < self::TREND_SIGNIFICANCE,
            'direction' => abs($slope) < 0.005 ? 'flat' : ($slope > 0 ? 'up' : 'down'),
        ];
    }

    /**
     * @param  list<array{shop: string, rating: int, body: string, at: CarbonImmutable}>  $reviews
     * @return array{available: bool, reason: ?string, praise: list<array{term: string, count_a: int, count_b: int, z: float}>, complaints: list<array{term: string, count_a: int, count_b: int, z: float}>}
     */
    private function terms(array $reviews): array
    {
        $poor = array_column(array_filter($reviews, fn (array $r): bool => $r['rating'] <= 2), 'body');
        $good = array_column(array_filter($reviews, fn (array $r): bool => $r['rating'] >= 4), 'body');

        if (count($poor) < self::TERMS_MIN_SIDE || count($good) < self::TERMS_MIN_SIDE) {
            return ['available' => false, 'reason' => __('Needs at least :count reviews rated 1–2 and :count rated 4–5.', ['count' => self::TERMS_MIN_SIDE]), 'praise' => [], 'complaints' => []];
        }

        $compared = LogOddsTerms::compare($poor, $good);

        return ['available' => true, 'reason' => null, 'praise' => $compared['b'], 'complaints' => $compared['a']];
    }
}
