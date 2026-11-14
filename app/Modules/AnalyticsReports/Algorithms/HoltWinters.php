<?php

namespace App\Modules\AnalyticsReports\Algorithms;

use InvalidArgumentException;

/**
 * Additive Holt–Winters exponential smoothing with a damped trend (level, trend and one seasonal
 * term per position in the cycle). The smoothing constants and the damping factor are chosen by grid
 * search to minimise the sum of squared one-step-ahead errors; states are initialised from the first two
 * cycles. Needs at least two full cycles.
 *
 *   forecast(h) = level + (φ + φ² + … + φʰ) · trend + seasonal[(n + h − 1) mod m]
 */
final class HoltWinters
{
    private const ALPHAS = [0.05, 0.1, 0.2, 0.3, 0.5, 0.7];

    private const BETAS = [0.01, 0.05, 0.1, 0.2];

    private const GAMMAS = [0.05, 0.1, 0.2, 0.4, 0.6];

    private const PHIS = [0.9, 0.98];

    /**
     * @param  list<float>  $seasonal  indexed by position in the cycle
     * @param  list<float>  $residuals  one-step-ahead errors from the second cycle on
     */
    private function __construct(
        public readonly float $alpha,
        public readonly float $beta,
        public readonly float $gamma,
        public readonly float $phi,
        public readonly int $period,
        public readonly int $observations,
        private readonly float $level,
        private readonly float $trend,
        private readonly array $seasonal,
        public readonly array $residuals,
        public readonly float $sse,
    ) {}

    /**
     * @param  list<float>  $y  the series in time order
     */
    public static function fit(array $y, int $period): self
    {
        $n = count($y);

        if ($period < 2 || $n < 2 * $period) {
            throw new InvalidArgumentException('Holt–Winters needs at least two full cycles of data.');
        }

        $first = array_sum(array_slice($y, 0, $period)) / $period;
        $second = array_sum(array_slice($y, $period, $period)) / $period;
        $trend0 = ($second - $first) / $period;
        $seasonal0 = [];

        for ($i = 0; $i < $period; $i++) {
            $seasonal0[$i] = $y[$i] - ($first + $trend0 * ($i - ($period - 1) / 2));
        }

        $centre = array_sum($seasonal0) / $period;
        $seasonal0 = array_map(fn (float $s): float => $s - $centre, $seasonal0);
        $level0 = $first + $trend0 * ($period - 1) / 2;

        $best = null;

        foreach (self::ALPHAS as $alpha) {
            foreach (self::BETAS as $beta) {
                foreach (self::GAMMAS as $gamma) {
                    foreach (self::PHIS as $phi) {
                        $run = self::run($y, $period, $level0, $trend0, $seasonal0, $alpha, $beta, $gamma, $phi);

                        if ($best === null || $run['sse'] < $best['sse'] - 1e-9) {
                            $best = $run + ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma, 'phi' => $phi];
                        }
                    }
                }
            }
        }

        return new self($best['alpha'], $best['beta'], $best['gamma'], $best['phi'], $period, $n, $best['level'], $best['trend'], $best['seasonal'], $best['residuals'], $best['sse']);
    }

    /**
     * @return list<float>
     */
    public function forecast(int $horizon): array
    {
        $predictions = [];
        $damped = 0.0;
        $power = 1.0;

        for ($h = 1; $h <= $horizon; $h++) {
            $power *= $this->phi;
            $damped += $power;
            $predictions[] = $this->level + $damped * $this->trend + $this->seasonal[($this->observations + $h - 1) % $this->period];
        }

        return $predictions;
    }

    /**
     * Number of parameters estimated (three smoothing constants, the damping factor, level, trend and seasonals).
     */
    public function parameterCount(): int
    {
        return 4 + 2 + $this->period;
    }

    /**
     * @param  list<float>  $y
     * @param  list<float>  $seasonal
     * @return array{sse: float, level: float, trend: float, seasonal: list<float>, residuals: list<float>}
     */
    private static function run(array $y, int $m, float $level, float $trend, array $seasonal, float $alpha, float $beta, float $gamma, float $phi): array
    {
        $sse = 0.0;
        $residuals = [];

        for ($t = $m, $n = count($y); $t < $n; $t++) {
            $slot = $t % $m;
            $error = $y[$t] - ($level + $phi * $trend + $seasonal[$slot]);
            $sse += $error * $error;
            $residuals[] = $error;

            $newLevel = $alpha * ($y[$t] - $seasonal[$slot]) + (1 - $alpha) * ($level + $phi * $trend);
            $trend = $beta * ($newLevel - $level) + (1 - $beta) * $phi * $trend;
            $seasonal[$slot] = $gamma * ($y[$t] - $newLevel) + (1 - $gamma) * $seasonal[$slot];
            $level = $newLevel;
        }

        return ['sse' => $sse, 'level' => $level, 'trend' => $trend, 'seasonal' => $seasonal, 'residuals' => $residuals];
    }
}
