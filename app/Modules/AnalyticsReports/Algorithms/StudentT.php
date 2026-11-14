<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * Student's t distribution without a statistics library: the regularised incomplete beta function
 * (Lentz continued fraction, as in Numerical Recipes) gives the CDF, bisection gives quantiles.
 * Used for p-values of regression slopes and for prediction-interval widths.
 */
final class StudentT
{
    /** @var list<float> */
    private const LANCZOS = [
        0.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313,
        -176.61502916214059, 12.507343278686905, -0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7,
    ];

    /**
     * P(T <= t) for df degrees of freedom.
     */
    public static function cdf(float $t, float $df): float
    {
        $tail = 0.5 * self::incompleteBeta($df / ($df + $t * $t), $df / 2, 0.5);

        return $t > 0 ? 1 - $tail : $tail;
    }

    /**
     * P(|T| >= |t|): the two-sided p-value.
     */
    public static function twoSidedP(float $t, float $df): float
    {
        return self::incompleteBeta($df / ($df + $t * $t), $df / 2, 0.5);
    }

    /**
     * The value q with P(T <= q) = p, for 0 < p < 1.
     */
    public static function quantile(float $p, float $df): float
    {
        if ($p === 0.5) {
            return 0.0;
        }

        if ($p < 0.5) {
            return -self::quantile(1 - $p, $df);
        }

        $low = 0.0;
        $high = 1.0;

        while (self::cdf($high, $df) < $p && $high < 1e8) {
            $high *= 2;
        }

        for ($i = 0; $i < 200; $i++) {
            $mid = ($low + $high) / 2;

            if (self::cdf($mid, $df) < $p) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    public static function logGamma(float $x): float
    {
        if ($x < 0.5) {
            return log(M_PI / abs(sin(M_PI * $x))) - self::logGamma(1 - $x);
        }

        $x -= 1;
        $a = self::LANCZOS[0];
        $t = $x + 7.5;

        for ($i = 1; $i < 9; $i++) {
            $a += self::LANCZOS[$i] / ($x + $i);
        }

        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($a);
    }

    public static function incompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0) {
            return 0.0;
        }

        if ($x >= 1) {
            return 1.0;
        }

        $front = exp(self::logGamma($a + $b) - self::logGamma($a) - self::logGamma($b) + $a * log($x) + $b * log(1 - $x));

        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $front * self::continuedFraction($x, $a, $b) / $a;
        }

        return 1 - $front * self::continuedFraction(1 - $x, $b, $a) / $b;
    }

    private static function continuedFraction(float $x, float $a, float $b): float
    {
        $tiny = 1e-300;
        $qab = $a + $b;
        $qap = $a + 1;
        $qam = $a - 1;
        $c = 1.0;
        $d = 1 - $qab * $x / $qap;
        $d = abs($d) < $tiny ? 1 / $tiny : 1 / $d;
        $h = $d;

        for ($m = 1; $m <= 300; $m++) {
            $m2 = 2 * $m;

            $step = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1 + $step * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1 + $step / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1 / $d;
            $h *= $d * $c;

            $step = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1 + $step * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1 + $step / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1 / $d;
            $change = $d * $c;
            $h *= $change;

            if (abs($change - 1) < 3e-12) {
                break;
            }
        }

        return $h;
    }
}
