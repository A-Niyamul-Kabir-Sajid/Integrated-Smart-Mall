<?php

namespace Tests\Unit\AnalyticsReports;

use App\Modules\AnalyticsReports\Algorithms\RidgeRegression;
use App\Modules\AnalyticsReports\Algorithms\StudentT;
use PHPUnit\Framework\TestCase;

/**
 * Regression and the t distribution against textbook values (Anscombe's quartet, set I, whose least
 * squares fit is published: intercept 3.0001, slope 0.5001, R² 0.6665, slope standard error 0.1179).
 */
class RegressionTest extends TestCase
{
    private const ANSCOMBE_X = [10, 8, 13, 9, 11, 14, 6, 4, 12, 7, 5];

    private const ANSCOMBE_Y = [8.04, 6.95, 7.58, 8.81, 8.33, 9.96, 7.24, 4.26, 10.84, 4.82, 5.68];

    /**
     * @return list<list<float>>
     */
    private function anscombeRows(): array
    {
        return array_map(fn (int $x): array => [(float) $x], self::ANSCOMBE_X);
    }

    public function test_an_exact_line_is_recovered(): void
    {
        $model = RidgeRegression::fit([[1.0], [2.0], [3.0], [4.0], [5.0]], [3.0, 5.0, 7.0, 9.0, 11.0]);
        $fit = $model->coefficients();

        $this->assertEqualsWithDelta(1.0, $fit['intercept'], 1e-9);
        $this->assertEqualsWithDelta(2.0, $fit['coefficients'][0], 1e-9);
        $this->assertEqualsWithDelta(1.0, $model->rSquared(), 1e-9);
        $this->assertEqualsWithDelta(13.0, $model->predict([6.0]), 1e-9);
    }

    public function test_ordinary_least_squares_matches_the_published_anscombe_fit(): void
    {
        $model = RidgeRegression::fit($this->anscombeRows(), self::ANSCOMBE_Y);
        $fit = $model->coefficients();

        $this->assertEqualsWithDelta(3.0001, $fit['intercept'], 0.001);
        $this->assertEqualsWithDelta(0.5001, $fit['coefficients'][0], 0.001);
        $this->assertEqualsWithDelta(0.6665, $model->rSquared(), 0.001);
        $this->assertEqualsWithDelta(0.1179, $model->standardErrors()[0], 0.001);
        $this->assertEqualsWithDelta(0.00217, $model->pValues()[0], 0.0003);
        $this->assertSame(9, $model->degreesOfFreedom());
        $this->assertEqualsWithDelta(0.6295, $model->adjustedRSquared(), 0.001);
    }

    public function test_two_features_are_fitted_together(): void
    {
        // y = 1 + 2a - 3b, exactly, with a and b not proportional.
        $rows = [[1.0, 0.0], [2.0, 1.0], [3.0, 0.0], [4.0, 2.0], [5.0, 1.0], [6.0, 3.0]];
        $y = array_map(fn (array $r): float => 1 + 2 * $r[0] - 3 * $r[1], $rows);

        $fit = RidgeRegression::fit($rows, $y)->coefficients();

        $this->assertEqualsWithDelta(1.0, $fit['intercept'], 1e-8);
        $this->assertEqualsWithDelta(2.0, $fit['coefficients'][0], 1e-8);
        $this->assertEqualsWithDelta(-3.0, $fit['coefficients'][1], 1e-8);
    }

    public function test_the_ridge_penalty_shrinks_the_coefficient_and_removes_the_standard_errors(): void
    {
        $plain = RidgeRegression::fit($this->anscombeRows(), self::ANSCOMBE_Y);
        $ridge = RidgeRegression::fit($this->anscombeRows(), self::ANSCOMBE_Y, 10.0);
        $heavy = RidgeRegression::fit($this->anscombeRows(), self::ANSCOMBE_Y, 1_000_000.0);

        $this->assertLessThan($plain->coefficients()['coefficients'][0], $ridge->coefficients()['coefficients'][0]);
        $this->assertGreaterThan(0.0, $ridge->coefficients()['coefficients'][0]);
        $this->assertEqualsWithDelta(0.0, $heavy->coefficients()['coefficients'][0], 0.001);
        $this->assertNull($ridge->standardErrors()[0]);
        $this->assertNull($ridge->pValues()[0]);
    }

    public function test_a_constant_feature_is_dropped_instead_of_failing(): void
    {
        $rows = [[1.0, 7.0], [2.0, 7.0], [3.0, 7.0], [4.0, 7.0]];
        $model = RidgeRegression::fit($rows, [2.0, 4.0, 6.0, 8.0]);

        $this->assertEqualsWithDelta(2.0, $model->coefficients()['coefficients'][0], 1e-9);
        $this->assertSame(0.0, $model->coefficients()['coefficients'][1]);
        $this->assertEqualsWithDelta(10.0, $model->predict([5.0, 7.0]), 1e-9);
    }

    public function test_the_prediction_interval_contains_the_point_and_widens_away_from_the_data(): void
    {
        $model = RidgeRegression::fit($this->anscombeRows(), self::ANSCOMBE_Y);

        [$lowNear, $highNear] = $model->predictionInterval([9.0], 0.95);
        [$lowFar, $highFar] = $model->predictionInterval([25.0], 0.95);

        $this->assertLessThan($model->predict([9.0]), $lowNear);
        $this->assertGreaterThan($model->predict([9.0]), $highNear);
        $this->assertGreaterThan($highNear - $lowNear, $highFar - $lowFar);
        // At the mean of x the 95% interval half-width is t(0.975, 9) * s * sqrt(1 + 1/11) = 2.262 * 1.2366 * 1.0445
        [$low, $high] = $model->predictionInterval([9.0], 0.95);
        $this->assertEqualsWithDelta(2.262 * 1.2366 * sqrt(1 + 1 / 11), ($high - $low) / 2, 0.01);
    }

    public function test_durbin_watson_is_low_for_a_curved_pattern_and_high_for_alternating_errors(): void
    {
        $x = array_map(fn (int $i): array => [(float) $i], range(1, 12));
        $drift = array_map(fn (int $i): float => ($i - 6.5) ** 2, range(1, 12));
        $alternating = array_map(fn (int $i): float => $i + ($i % 2 === 0 ? 1.0 : -1.0), range(1, 12));

        $this->assertLessThan(1.0, RidgeRegression::fit($x, $drift)->durbinWatson());
        $this->assertGreaterThan(2.5, RidgeRegression::fit($x, $alternating)->durbinWatson());
    }

    public function test_the_t_distribution_matches_table_values(): void
    {
        $this->assertEqualsWithDelta(0.5, StudentT::cdf(0.0, 7), 1e-12);
        $this->assertEqualsWithDelta(0.05, StudentT::twoSidedP(2.228139, 10), 0.0005);
        $this->assertEqualsWithDelta(0.01, StudentT::twoSidedP(3.169273, 10), 0.0002);
        $this->assertEqualsWithDelta(2.228139, StudentT::quantile(0.975, 10), 0.001);
        $this->assertEqualsWithDelta(-2.228139, StudentT::quantile(0.025, 10), 0.001);
        $this->assertEqualsWithDelta(1.96, StudentT::quantile(0.975, 100000), 0.002);
        $this->assertEqualsWithDelta(log(24), StudentT::logGamma(5.0), 1e-9);
        $this->assertEqualsWithDelta(0.5, StudentT::incompleteBeta(0.5, 2.0, 2.0), 1e-9);
    }
}
