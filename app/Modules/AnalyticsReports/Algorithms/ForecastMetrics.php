<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * Error measures for forecasts. Lower is better for all of them.
 *
 * MASE divides the error by the in-sample error of the seasonal naive forecast (value one season ago),
 * so 1.0 means "no better than repeating last week" and below 1.0 means better (Hyndman and Koehler).
 */
final class ForecastMetrics
{
    /**
     * @param  list<float>  $actual
     * @param  list<float>  $predicted
     */
    public static function mae(array $actual, array $predicted): float
    {
        return array_sum(array_map(fn (float $a, float $p): float => abs($a - $p), $actual, $predicted)) / count($actual);
    }

    /**
     * @param  list<float>  $actual
     * @param  list<float>  $predicted
     */
    public static function rmse(array $actual, array $predicted): float
    {
        return sqrt(array_sum(array_map(fn (float $a, float $p): float => ($a - $p) ** 2, $actual, $predicted)) / count($actual));
    }

    /**
     * Symmetric mean absolute percentage error in percent; a day where both values are 0 counts as 0.
     *
     * @param  list<float>  $actual
     * @param  list<float>  $predicted
     */
    public static function smape(array $actual, array $predicted): float
    {
        $sum = 0.0;

        foreach ($actual as $i => $a) {
            $denominator = abs($a) + abs($predicted[$i]);
            $sum += $denominator < 1e-12 ? 0.0 : 2 * abs($a - $predicted[$i]) / $denominator;
        }

        return 100 * $sum / count($actual);
    }

    /**
     * Mean error (positive = the forecast is too high on average).
     *
     * @param  list<float>  $actual
     * @param  list<float>  $predicted
     */
    public static function bias(array $actual, array $predicted): float
    {
        return array_sum(array_map(fn (float $a, float $p): float => $p - $a, $actual, $predicted)) / count($actual);
    }

    /**
     * @param  list<float>  $actual
     * @param  list<float>  $predicted
     * @param  list<float>  $training  the series the forecast was learned from
     */
    public static function mase(array $actual, array $predicted, array $training, int $season): ?float
    {
        $count = count($training) - $season;

        if ($count < 1) {
            return null;
        }

        $scale = 0.0;

        for ($i = $season; $i < count($training); $i++) {
            $scale += abs($training[$i] - $training[$i - $season]);
        }

        $scale /= $count;

        return $scale < 1e-12 ? null : self::mae($actual, $predicted) / $scale;
    }
}
