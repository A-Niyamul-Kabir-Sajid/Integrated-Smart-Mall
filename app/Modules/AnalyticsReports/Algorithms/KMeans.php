<?php

namespace App\Modules\AnalyticsReports\Algorithms;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * k-means clustering (Lloyd's algorithm) with k-means++ seeding and several restarts; the restart with
 * the lowest within-cluster sum of squares wins. The random generator is seeded, so the same points
 * always give the same clusters. The number of clusters can be chosen by the mean silhouette
 * coefficient (Rousseeuw, 1987). Features should be standardised first (see Standardizer).
 */
final class KMeans
{
    /**
     * @param  list<list<float>>  $points
     * @return array{labels: list<int>, centroids: list<list<float>>, inertia: float}
     */
    public static function cluster(array $points, int $k, int $seed = 2026, int $restarts = 10, int $maxIterations = 100): array
    {
        $k = max(1, min($k, count(array_unique(array_map(fn (array $p): string => implode('|', $p), $points)))));
        $best = null;

        for ($restart = 0; $restart < $restarts; $restart++) {
            $run = self::lloyd($points, $k, new Randomizer(new Mt19937($seed + $restart)), $maxIterations);

            if ($best === null || $run['inertia'] < $best['inertia'] - 1e-12) {
                $best = $run;
            }
        }

        return $best;
    }

    /**
     * Tries each k from $minK to $maxK and keeps the clustering with the highest mean silhouette.
     *
     * @param  list<list<float>>  $points
     * @return array{labels: list<int>, centroids: list<list<float>>, inertia: float, k: int, silhouette: float}|null
     */
    public static function best(array $points, int $minK = 2, int $maxK = 4, int $seed = 2026): ?array
    {
        $maxK = min($maxK, count($points) - 1);
        $best = null;

        for ($k = $minK; $k <= $maxK; $k++) {
            $result = self::cluster($points, $k, $seed);

            if (count(array_unique($result['labels'])) < 2) {
                continue;
            }

            $silhouette = self::silhouette($points, $result['labels']);

            if ($best === null || $silhouette > $best['silhouette'] + 1e-9) {
                $best = $result + ['k' => $k, 'silhouette' => $silhouette];
            }
        }

        return $best;
    }

    /**
     * Mean silhouette: for each point (b − a) / max(a, b), where a is the mean distance to its own cluster
     * and b the smallest mean distance to another cluster; a point alone in its cluster scores 0.
     *
     * @param  list<list<float>>  $points
     * @param  list<int>  $labels
     */
    public static function silhouette(array $points, array $labels): float
    {
        $total = 0.0;
        $clusters = array_unique($labels);

        foreach ($points as $i => $point) {
            $sums = array_fill_keys($clusters, 0.0);
            $counts = array_fill_keys($clusters, 0);

            foreach ($points as $j => $other) {
                if ($i !== $j) {
                    $sums[$labels[$j]] += self::distance($point, $other);
                    $counts[$labels[$j]]++;
                }
            }

            if ($counts[$labels[$i]] === 0) {
                continue;
            }

            $a = $sums[$labels[$i]] / $counts[$labels[$i]];
            $b = INF;

            foreach ($clusters as $cluster) {
                if ($cluster !== $labels[$i] && $counts[$cluster] > 0) {
                    $b = min($b, $sums[$cluster] / $counts[$cluster]);
                }
            }

            if (is_finite($b) && max($a, $b) > 1e-12) {
                $total += ($b - $a) / max($a, $b);
            }
        }

        return $total / count($points);
    }

    /**
     * @param  list<list<float>>  $points
     * @return array{labels: list<int>, centroids: list<list<float>>, inertia: float}
     */
    private static function lloyd(array $points, int $k, Randomizer $random, int $maxIterations): array
    {
        $centroids = self::seedCentroids($points, $k, $random);
        $labels = array_fill(0, count($points), -1);

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $changed = false;

            foreach ($points as $i => $point) {
                $nearest = self::nearest($point, $centroids);

                if ($labels[$i] !== $nearest) {
                    $labels[$i] = $nearest;
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }

            foreach ($centroids as $c => $centroid) {
                $members = array_keys(array_filter($labels, fn (int $label): bool => $label === $c));

                if ($members === []) {
                    continue;
                }

                $dimension = count($centroid);

                for ($d = 0; $d < $dimension; $d++) {
                    $centroids[$c][$d] = array_sum(array_map(fn (int $i): float => $points[$i][$d], $members)) / count($members);
                }
            }
        }

        $inertia = 0.0;

        foreach ($points as $i => $point) {
            $inertia += self::distance($point, $centroids[$labels[$i]]) ** 2;
        }

        return ['labels' => $labels, 'centroids' => $centroids, 'inertia' => $inertia];
    }

    /**
     * k-means++: the first centre is random, each next centre is drawn with probability proportional to
     * the squared distance to the nearest centre already chosen.
     *
     * @param  list<list<float>>  $points
     * @return list<list<float>>
     */
    private static function seedCentroids(array $points, int $k, Randomizer $random): array
    {
        $centroids = [$points[$random->getInt(0, count($points) - 1)]];

        while (count($centroids) < $k) {
            $weights = array_map(fn (array $p): float => self::distance($p, $centroids[self::nearest($p, $centroids)]) ** 2, $points);
            $total = array_sum($weights);

            if ($total < 1e-12) {
                break;
            }

            $target = $random->getInt(0, 1_000_000) / 1_000_000 * $total;
            $running = 0.0;
            $chosen = count($points) - 1;

            foreach ($weights as $i => $weight) {
                $running += $weight;

                if ($running >= $target && $weight > 0) {
                    $chosen = $i;
                    break;
                }
            }

            $centroids[] = $points[$chosen];
        }

        return $centroids;
    }

    /**
     * @param  list<float>  $point
     * @param  list<list<float>>  $centroids
     */
    private static function nearest(array $point, array $centroids): int
    {
        $best = 0;
        $bestDistance = INF;

        foreach ($centroids as $c => $centroid) {
            $distance = self::distance($point, $centroid);

            if ($distance < $bestDistance - 1e-12) {
                $best = $c;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private static function distance(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $d => $value) {
            $sum += ($value - $b[$d]) ** 2;
        }

        return sqrt($sum);
    }
}
