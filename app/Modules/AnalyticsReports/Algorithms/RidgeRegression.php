<?php

namespace App\Modules\AnalyticsReports\Algorithms;

use InvalidArgumentException;

/**
 * Multiple linear regression by the normal equations, with an optional ridge (L2) penalty.
 *
 * Features are centred and scaled to unit variance before the penalty is applied, so the penalty treats
 * every feature alike, and the intercept is never penalised. With lambda = 0 this is ordinary least
 * squares and the usual inference is available (standard errors, t statistics, two-sided p-values,
 * adjusted R², prediction intervals). With lambda > 0 the coefficients are shrunk and the standard
 * errors are not reported: the interval is then an approximation and labelled as such by the caller.
 * Constant features carry no information and are dropped (their coefficient is 0).
 *
 * The solve uses Cholesky factorisation of ZᵀZ + λI (see Matrix).
 */
final class RidgeRegression
{
    /**
     * @param  list<int>  $kept  indexes of the features that vary
     * @param  list<float>  $mean
     * @param  list<float>  $scale
     * @param  list<float>  $beta  coefficients in the standardised space, one per kept feature
     * @param  list<list<float>>  $inverse  (ZᵀZ + λI)⁻¹
     * @param  list<float>  $residuals
     */
    private function __construct(
        private readonly int $featureCount,
        private readonly array $kept,
        private readonly array $mean,
        private readonly array $scale,
        private readonly array $beta,
        private readonly float $yMean,
        private readonly array $inverse,
        public readonly float $lambda,
        public readonly int $observations,
        private readonly array $residuals,
        public readonly float $rss,
        public readonly float $tss,
    ) {}

    /**
     * @param  list<list<float>>  $x  one row of features per observation, without a constant column
     * @param  list<float>  $y
     */
    public static function fit(array $x, array $y, float $lambda = 0.0): self
    {
        $n = count($y);

        if ($n === 0 || count($x) !== $n) {
            throw new InvalidArgumentException('Features and targets must have the same, non-zero length.');
        }

        $featureCount = count($x[0]);
        $mean = $scale = $kept = [];

        for ($j = 0; $j < $featureCount; $j++) {
            $column = array_column($x, $j);
            $mean[$j] = array_sum($column) / $n;
            $variance = array_sum(array_map(fn (float $v): float => ($v - $mean[$j]) ** 2, $column)) / $n;
            $scale[$j] = $variance > 1e-12 ? sqrt($variance) : 1.0;

            if ($variance > 1e-12) {
                $kept[] = $j;
            }
        }

        $yMean = array_sum($y) / $n;
        $z = [];

        foreach ($x as $row) {
            $z[] = array_map(fn (int $j): float => ($row[$j] - $mean[$j]) / $scale[$j], $kept);
        }

        $p = count($kept);
        $beta = $inverse = [];

        if ($p > 0) {
            $gram = array_fill(0, $p, array_fill(0, $p, 0.0));
            $moment = array_fill(0, $p, 0.0);

            foreach ($z as $i => $row) {
                $centred = $y[$i] - $yMean;

                for ($a = 0; $a < $p; $a++) {
                    $moment[$a] += $row[$a] * $centred;

                    for ($b = 0; $b <= $a; $b++) {
                        $gram[$a][$b] += $row[$a] * $row[$b];
                    }
                }
            }

            for ($a = 0; $a < $p; $a++) {
                for ($b = 0; $b < $a; $b++) {
                    $gram[$b][$a] = $gram[$a][$b];
                }

                $gram[$a][$a] += $lambda;
            }

            $beta = Matrix::solve($gram, $moment);
            $inverse = Matrix::inverse($gram);
        }

        $residuals = [];
        $rss = $tss = 0.0;

        foreach ($z as $i => $row) {
            $fitted = $yMean;

            foreach ($beta as $a => $coefficient) {
                $fitted += $coefficient * $row[$a];
            }

            $residuals[] = $y[$i] - $fitted;
            $rss += ($y[$i] - $fitted) ** 2;
            $tss += ($y[$i] - $yMean) ** 2;
        }

        return new self($featureCount, $kept, $mean, $scale, $beta, $yMean, $inverse, $lambda, $n, $residuals, $rss, $tss);
    }

    /**
     * @param  list<float>  $row
     */
    public function predict(array $row): float
    {
        $value = $this->yMean;

        foreach ($this->kept as $a => $j) {
            $value += $this->beta[$a] * (($row[$j] - $this->mean[$j]) / $this->scale[$j]);
        }

        return $value;
    }

    /**
     * The intercept and one coefficient per feature, in the units of the original features.
     *
     * @return array{intercept: float, coefficients: list<float>}
     */
    public function coefficients(): array
    {
        $coefficients = array_fill(0, $this->featureCount, 0.0);
        $intercept = $this->yMean;

        foreach ($this->kept as $a => $j) {
            $coefficients[$j] = $this->beta[$a] / $this->scale[$j];
            $intercept -= $coefficients[$j] * $this->mean[$j];
        }

        return ['intercept' => $intercept, 'coefficients' => $coefficients];
    }

    public function degreesOfFreedom(): int
    {
        return $this->observations - count($this->kept) - 1;
    }

    /**
     * Residual variance RSS / (n - k - 1); null without spare degrees of freedom.
     */
    public function residualVariance(): ?float
    {
        $dof = $this->degreesOfFreedom();

        return $dof > 0 ? $this->rss / $dof : null;
    }

    public function rSquared(): float
    {
        if ($this->tss < 1e-12) {
            return $this->rss < 1e-12 ? 1.0 : 0.0;
        }

        return 1 - $this->rss / $this->tss;
    }

    public function adjustedRSquared(): ?float
    {
        $dof = $this->degreesOfFreedom();

        if ($dof <= 0 || $this->tss < 1e-12) {
            return null;
        }

        return 1 - ($this->rss / $dof) / ($this->tss / ($this->observations - 1));
    }

    /**
     * Standard errors of the coefficients (original units); ordinary least squares only.
     *
     * @return list<?float>
     */
    public function standardErrors(): array
    {
        $variance = $this->residualVariance();
        $errors = array_fill(0, $this->featureCount, null);

        if ($variance === null || $this->lambda > 0) {
            return $errors;
        }

        foreach ($this->kept as $a => $j) {
            $errors[$j] = sqrt($variance * $this->inverse[$a][$a]) / $this->scale[$j];
        }

        return $errors;
    }

    /**
     * Two-sided p-values of the hypothesis "this coefficient is 0"; ordinary least squares only.
     *
     * @return list<?float>
     */
    public function pValues(): array
    {
        $coefficients = $this->coefficients()['coefficients'];
        $dof = $this->degreesOfFreedom();
        $values = [];

        foreach ($this->standardErrors() as $j => $error) {
            $values[$j] = $error === null || $error <= 0 ? null : StudentT::twoSidedP($coefficients[$j] / $error, $dof);
        }

        return $values;
    }

    /**
     * Interval for one new observation: ŷ ± t · s · sqrt(1 + 1/n + zᵀ(ZᵀZ + λI)⁻¹z). Exact for ordinary
     * least squares under independent normal errors, an approximation when lambda > 0.
     *
     * @param  list<float>  $row
     * @return array{0: float, 1: float}
     */
    public function predictionInterval(array $row, float $level = 0.8): array
    {
        $point = $this->predict($row);
        $variance = $this->residualVariance();

        if ($variance === null) {
            return [$point, $point];
        }

        $z = array_map(fn (int $j): float => ($row[$j] - $this->mean[$j]) / $this->scale[$j], $this->kept);
        $leverage = 1 / $this->observations;

        foreach ($z as $a => $za) {
            foreach ($z as $b => $zb) {
                $leverage += $za * $this->inverse[$a][$b] * $zb;
            }
        }

        $half = StudentT::quantile(0.5 + $level / 2, $this->degreesOfFreedom()) * sqrt($variance * (1 + $leverage));

        return [$point - $half, $point + $half];
    }

    /**
     * Durbin–Watson statistic of the residuals taken in the order given (about 2 = no first-order
     * autocorrelation, towards 0 = positive autocorrelation, towards 4 = negative).
     */
    public function durbinWatson(): ?float
    {
        $sumSquares = array_sum(array_map(fn (float $e): float => $e * $e, $this->residuals));

        if ($sumSquares < 1e-12 || count($this->residuals) < 2) {
            return null;
        }

        $differences = 0.0;

        for ($i = 1, $count = count($this->residuals); $i < $count; $i++) {
            $differences += ($this->residuals[$i] - $this->residuals[$i - 1]) ** 2;
        }

        return $differences / $sumSquares;
    }
}
