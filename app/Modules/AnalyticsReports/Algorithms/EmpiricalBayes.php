<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * Shrinkage for comparing groups of different size (a shop with two 5-star reviews must not outrank a
 * shop with two hundred 4.8-star ones).
 *
 * Normal–normal empirical Bayes: each group's mean is pulled towards the grand mean with a prior weight
 * κ = σ² / τ², where σ² is the pooled within-group variance and τ² the between-group variance estimated
 * by the method of moments (DerSimonian–Laird form). The estimate is (κ·μ + sum) / (κ + n). When the data
 * cannot support an estimate the fallback weight is used. Also provides the Wilson score lower bound for
 * a proportion.
 */
final class EmpiricalBayes
{
    public const FALLBACK_WEIGHT = 5.0;

    private const MIN_WEIGHT = 0.5;

    private const MAX_WEIGHT = 50.0;

    /**
     * @param  array<int|string, array{n: int, sum: float, sum_squares: float}>  $groups
     * @return array{grand_mean: float, prior_weight: float, estimated: bool, estimates: array<int|string, float>}
     */
    public static function shrink(array $groups): array
    {
        $groups = array_filter($groups, fn (array $g): bool => $g['n'] > 0);
        $total = array_sum(array_column($groups, 'n'));

        if ($total === 0) {
            return ['grand_mean' => 0.0, 'prior_weight' => self::FALLBACK_WEIGHT, 'estimated' => false, 'estimates' => []];
        }

        $grandMean = array_sum(array_column($groups, 'sum')) / $total;
        $count = count($groups);
        $weight = self::FALLBACK_WEIGHT;
        $estimated = false;

        if ($count >= 2 && $total > $count) {
            $within = 0.0;
            $between = 0.0;

            foreach ($groups as $g) {
                $within += $g['sum_squares'] - $g['sum'] ** 2 / $g['n'];
                $between += $g['n'] * ($g['sum'] / $g['n'] - $grandMean) ** 2;
            }

            $sigma2 = $within / ($total - $count);
            $denominator = $total - array_sum(array_map(fn (array $g): float => $g['n'] ** 2, $groups)) / $total;
            $tau2 = $denominator > 0 ? max(0.0, ($between - ($count - 1) * $sigma2) / $denominator) : 0.0;

            if ($sigma2 > 1e-12) {
                $weight = $tau2 > 1e-12 ? min(self::MAX_WEIGHT, max(self::MIN_WEIGHT, $sigma2 / $tau2)) : self::MAX_WEIGHT;
                $estimated = true;
            }
        }

        $estimates = array_map(fn (array $g): float => ($weight * $grandMean + $g['sum']) / ($weight + $g['n']), $groups);

        return ['grand_mean' => $grandMean, 'prior_weight' => $weight, 'estimated' => $estimated, 'estimates' => $estimates];
    }

    /**
     * Lower end of the Wilson score interval for successes out of trials (z = 1.96 is 95%).
     */
    public static function wilsonLower(int $successes, int $trials, float $z = 1.96): ?float
    {
        if ($trials <= 0) {
            return null;
        }

        $p = $successes / $trials;
        $denominator = 1 + $z * $z / $trials;
        $centre = $p + $z * $z / (2 * $trials);
        $margin = $z * sqrt($p * (1 - $p) / $trials + $z * $z / (4 * $trials * $trials));

        return max(0.0, ($centre - $margin) / $denominator);
    }
}
