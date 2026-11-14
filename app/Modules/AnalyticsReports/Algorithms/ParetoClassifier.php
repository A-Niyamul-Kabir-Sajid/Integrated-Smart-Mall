<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * ABC–XYZ classification of products. ABC is the Pareto split of revenue: items are ranked by revenue
 * and the ones that bring the first 80% are A, the next 15% are B, the rest C. XYZ is demand
 * variability: the coefficient of variation (standard deviation / mean) of weekly units sold, X below
 * 0.5 (steady), Y below 1.0 (varying), Z otherwise or when nothing was sold. AX means "important and
 * predictable", CZ "minor and erratic".
 */
final class ParetoClassifier
{
    /**
     * @param  array<int|string, float>  $revenue
     * @return array<int|string, string> class per key
     */
    public static function abc(array $revenue, float $aShare = 0.80, float $bShare = 0.95): array
    {
        arsort($revenue);
        $total = array_sum(array_filter($revenue, fn (float $r): bool => $r > 0));
        $classes = [];
        $before = 0.0;

        foreach ($revenue as $key => $value) {
            if ($value <= 0 || $total <= 0) {
                $classes[$key] = 'C';

                continue;
            }

            $share = $before / $total;
            $classes[$key] = $share < $aShare - 1e-12 ? 'A' : ($share < $bShare - 1e-12 ? 'B' : 'C');
            $before += $value;
        }

        return $classes;
    }

    /**
     * @param  list<float>  $weeklyUnits
     */
    public static function xyz(array $weeklyUnits): string
    {
        $count = count($weeklyUnits);
        $mean = $count > 0 ? array_sum($weeklyUnits) / $count : 0.0;

        if ($mean <= 1e-12) {
            return 'Z';
        }

        $sd = sqrt(array_sum(array_map(fn (float $u): float => ($u - $mean) ** 2, $weeklyUnits)) / $count);
        $cv = $sd / $mean;

        return $cv < 0.5 ? 'X' : ($cv < 1.0 ? 'Y' : 'Z');
    }
}
