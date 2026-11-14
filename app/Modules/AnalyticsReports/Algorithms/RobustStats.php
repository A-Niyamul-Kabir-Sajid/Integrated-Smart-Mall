<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * Median-based statistics that a few extreme values cannot distort, and the z-score standardisation
 * used before clustering.
 */
final class RobustStats
{
    /**
     * @param  list<float>  $values
     */
    public static function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Median absolute deviation.
     *
     * @param  list<float>  $values
     */
    public static function mad(array $values): float
    {
        $median = self::median($values);

        return self::median(array_map(fn (float $v): float => abs($v - $median), $values));
    }

    /**
     * Modified z-scores 0.6745 (x − median) / MAD (Iglewicz and Hoaglin); values beyond ±3.5 are
     * conventionally outliers. When more than half the values are equal (MAD = 0) the mean absolute
     * deviation is used instead: 0.7979 (x − median) / meanAD.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    public static function modifiedZScores(array $values): array
    {
        $median = self::median($values);
        $mad = self::mad($values);

        if ($mad > 1e-12) {
            return array_map(fn (float $v): float => 0.6745 * ($v - $median) / $mad, $values);
        }

        $meanAd = array_sum(array_map(fn (float $v): float => abs($v - $median), $values)) / count($values);

        return array_map(fn (float $v): float => $meanAd > 1e-12 ? ($v - $median) / (1.253314 * $meanAd) : 0.0, $values);
    }

    /**
     * Column-wise z-scores (population standard deviation); a column that does not vary becomes 0.
     *
     * @param  list<list<float>>  $points
     * @return list<list<float>>
     */
    public static function standardise(array $points): array
    {
        $count = count($points);
        $result = $points;

        for ($d = 0, $dimensions = count($points[0] ?? []); $d < $dimensions; $d++) {
            $column = array_column($points, $d);
            $mean = array_sum($column) / $count;
            $sd = sqrt(array_sum(array_map(fn (float $v): float => ($v - $mean) ** 2, $column)) / $count);

            foreach ($points as $i => $point) {
                $result[$i][$d] = $sd > 1e-12 ? ($point[$d] - $mean) / $sd : 0.0;
            }
        }

        return $result;
    }
}
