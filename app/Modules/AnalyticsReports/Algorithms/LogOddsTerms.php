<?php

namespace App\Modules\AnalyticsReports\Algorithms;

/**
 * Finds the words that set one group of texts apart from another (for example low-rated reviews
 * against high-rated ones) with the weighted log-odds ratio and an informative Dirichlet prior
 * (Monroe, Colaresi and Quinn, 2008). The prior is the word's frequency in both groups together, so
 * common words are not mistaken for signals; the score is the log-odds ratio divided by its standard
 * deviation, a z-score. Words are lower-cased, short words and common English words are dropped.
 * English only.
 */
final class LogOddsTerms
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'her', 'was', 'one', 'our', 'out', 'has', 'have', 'had',
        'his', 'how', 'its', 'may', 'new', 'now', 'old', 'see', 'two', 'who', 'did', 'get', 'got', 'let', 'say', 'she', 'too', 'use',
        'this', 'that', 'with', 'from', 'they', 'were', 'what', 'when', 'will', 'your', 'very', 'been', 'than', 'then', 'them',
        'there', 'their', 'would', 'about', 'which', 'just', 'also', 'into', 'over', 'some', 'more', 'much', 'only', 'here',
        'shop', 'store', 'bought', 'buy',
    ];

    /**
     * @return list<string>
     */
    public static function tokenise(string $text): array
    {
        preg_match_all('/[a-z]{3,}/', mb_strtolower($text), $matches);

        return array_values(array_filter($matches[0], fn (string $word): bool => ! in_array($word, self::STOP_WORDS, true)));
    }

    /**
     * @param  list<string>  $groupA
     * @param  list<string>  $groupB
     * @return array{a: list<array{term: string, count_a: int, count_b: int, z: float}>, b: list<array{term: string, count_a: int, count_b: int, z: float}>}
     */
    public static function compare(array $groupA, array $groupB, int $top = 5, int $minCount = 2, float $priorStrength = 10.0, float $minZ = 1.0): array
    {
        $countsA = self::counts($groupA);
        $countsB = self::counts($groupB);
        $totalA = array_sum($countsA);
        $totalB = array_sum($countsB);
        $total = $totalA + $totalB;
        $a = $b = [];

        if ($totalA === 0 || $totalB === 0) {
            return ['a' => $a, 'b' => $b];
        }

        $alphaSum = $priorStrength;

        foreach (array_unique([...array_keys($countsA), ...array_keys($countsB)]) as $term) {
            $ya = $countsA[$term] ?? 0;
            $yb = $countsB[$term] ?? 0;

            if ($ya + $yb < $minCount) {
                continue;
            }

            $prior = $alphaSum * ($ya + $yb) / $total;
            $oddsA = log(($ya + $prior) / ($totalA + $alphaSum - $ya - $prior));
            $oddsB = log(($yb + $prior) / ($totalB + $alphaSum - $yb - $prior));
            $z = ($oddsA - $oddsB) / sqrt(1 / ($ya + $prior) + 1 / ($yb + $prior));
            $row = ['term' => (string) $term, 'count_a' => $ya, 'count_b' => $yb, 'z' => round($z, 2)];

            if ($z >= $minZ) {
                $a[] = $row;
            } elseif ($z <= -$minZ) {
                $b[] = $row;
            }
        }

        usort($a, fn (array $x, array $y): int => [$y['z'], $x['term']] <=> [$x['z'], $y['term']]);
        usort($b, fn (array $x, array $y): int => [$x['z'], $x['term']] <=> [$y['z'], $y['term']]);

        return ['a' => array_slice($a, 0, $top), 'b' => array_slice($b, 0, $top)];
    }

    /**
     * @param  list<string>  $texts
     * @return array<string, int>
     */
    private static function counts(array $texts): array
    {
        $counts = [];

        foreach ($texts as $text) {
            foreach (self::tokenise($text) as $word) {
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
