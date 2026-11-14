<?php

namespace App\Modules\AnalyticsReports\Algorithms;

use InvalidArgumentException;

/**
 * Small dense linear algebra for the regression: Cholesky factorisation of a symmetric positive
 * definite matrix, linear solves and inverses from it. Matrices are lists of rows.
 */
final class Matrix
{
    /**
     * Lower-triangular L with L Lᵀ = A. Throws when A is not (numerically) positive definite.
     *
     * @param  list<list<float>>  $a
     * @return list<list<float>>
     */
    public static function cholesky(array $a): array
    {
        $n = count($a);
        $l = array_fill(0, $n, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j <= $i; $j++) {
                $sum = $a[$i][$j];

                for ($k = 0; $k < $j; $k++) {
                    $sum -= $l[$i][$k] * $l[$j][$k];
                }

                if ($i === $j) {
                    if ($sum <= 1e-12) {
                        throw new InvalidArgumentException('The matrix is not positive definite (the features are linearly dependent).');
                    }

                    $l[$i][$j] = sqrt($sum);
                } else {
                    $l[$i][$j] = $sum / $l[$j][$j];
                }
            }
        }

        return $l;
    }

    /**
     * Solves A x = b for symmetric positive definite A.
     *
     * @param  list<list<float>>  $a
     * @param  list<float>  $b
     * @return list<float>
     */
    public static function solve(array $a, array $b): array
    {
        return self::solveWithFactor(self::cholesky($a), $b);
    }

    /**
     * @param  list<list<float>>  $a
     * @return list<list<float>>
     */
    public static function inverse(array $a): array
    {
        $n = count($a);
        $factor = self::cholesky($a);
        $columns = [];

        for ($c = 0; $c < $n; $c++) {
            $unit = array_fill(0, $n, 0.0);
            $unit[$c] = 1.0;
            $columns[$c] = self::solveWithFactor($factor, $unit);
        }

        $inverse = [];

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $inverse[$r][$c] = $columns[$c][$r];
            }
        }

        return $inverse;
    }

    /**
     * @param  list<list<float>>  $l
     * @param  list<float>  $b
     * @return list<float>
     */
    private static function solveWithFactor(array $l, array $b): array
    {
        $n = count($l);
        $y = array_fill(0, $n, 0.0);

        for ($i = 0; $i < $n; $i++) {
            $sum = $b[$i];

            for ($k = 0; $k < $i; $k++) {
                $sum -= $l[$i][$k] * $y[$k];
            }

            $y[$i] = $sum / $l[$i][$i];
        }

        $x = array_fill(0, $n, 0.0);

        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $y[$i];

            for ($k = $i + 1; $k < $n; $k++) {
                $sum -= $l[$k][$i] * $x[$k];
            }

            $x[$i] = $sum / $l[$i][$i];
        }

        return $x;
    }
}
