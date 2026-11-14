<?php

namespace Tests\Unit\AnalyticsReports;

use App\Modules\AnalyticsReports\Algorithms\EmpiricalBayes;
use App\Modules\AnalyticsReports\Algorithms\KMeans;
use App\Modules\AnalyticsReports\Algorithms\LogOddsTerms;
use App\Modules\AnalyticsReports\Algorithms\ParetoClassifier;
use App\Modules\AnalyticsReports\Algorithms\RobustStats;
use PHPUnit\Framework\TestCase;

class ComparisonAlgorithmsTest extends TestCase
{
    public function test_median_and_mad_resist_an_extreme_value(): void
    {
        $values = [1.0, 2.0, 3.0, 4.0, 100.0];

        $this->assertSame(3.0, RobustStats::median($values));
        $this->assertSame(2.5, RobustStats::median([1.0, 2.0, 3.0, 4.0]));
        $this->assertSame(1.0, RobustStats::mad($values));

        $scores = RobustStats::modifiedZScores($values);
        $this->assertEqualsWithDelta(0.0, $scores[2], 1e-12);
        $this->assertEqualsWithDelta(0.6745 * 97, $scores[4], 1e-9);
    }

    public function test_modified_z_scores_fall_back_when_most_values_are_equal(): void
    {
        $scores = RobustStats::modifiedZScores([5.0, 5.0, 5.0, 5.0, 20.0]);

        $this->assertGreaterThan(3.5, $scores[4]);
        $this->assertSame(0.0, $scores[0]);
        $this->assertSame([0.0, 0.0, 0.0], RobustStats::modifiedZScores([4.0, 4.0, 4.0]));
    }

    public function test_standardise_gives_zero_mean_and_unit_variance_columns(): void
    {
        $z = RobustStats::standardise([[1.0, 5.0], [2.0, 5.0], [3.0, 5.0]]);

        $this->assertEqualsWithDelta(0.0, array_sum(array_column($z, 0)), 1e-12);
        $this->assertEqualsWithDelta(-1.2247, $z[0][0], 0.0001);
        $this->assertSame(0.0, $z[1][1]);
    }

    public function test_k_means_separates_two_blobs_and_chooses_two_clusters(): void
    {
        $points = [[0.0, 0.0], [0.2, 0.1], [0.1, 0.3], [0.3, 0.2], [10.0, 10.0], [10.2, 10.1], [9.9, 10.3], [10.1, 9.8]];

        $best = KMeans::best($points, 2, 4);

        $this->assertSame(2, $best['k']);
        $this->assertGreaterThan(0.9, $best['silhouette']);
        $this->assertSame(1, count(array_unique(array_slice($best['labels'], 0, 4))));
        $this->assertSame(1, count(array_unique(array_slice($best['labels'], 4))));
        $this->assertNotSame($best['labels'][0], $best['labels'][4]);
        $this->assertSame($best, KMeans::best($points, 2, 4));
    }

    public function test_k_means_copes_with_fewer_distinct_points_than_clusters(): void
    {
        $result = KMeans::cluster([[1.0, 1.0], [1.0, 1.0], [2.0, 2.0]], 5);

        $this->assertCount(3, $result['labels']);
        $this->assertSame(2, count(array_unique($result['labels'])));
        $this->assertNull(KMeans::best([[1.0], [1.0]], 2, 4));
    }

    public function test_empirical_bayes_pulls_a_small_group_towards_the_mean(): void
    {
        $groups = [
            'few' => ['n' => 2, 'sum' => 10.0, 'sum_squares' => 50.0],
            'many' => ['n' => 100, 'sum' => 480.0, 'sum_squares' => 2330.0],
            'mid' => ['n' => 20, 'sum' => 70.0, 'sum_squares' => 260.0],
        ];

        $result = EmpiricalBayes::shrink($groups);

        $this->assertEqualsWithDelta(560 / 122, $result['grand_mean'], 1e-9);
        $this->assertGreaterThan($result['grand_mean'], $result['estimates']['few']);
        $this->assertLessThan(5.0, $result['estimates']['few']);
        $this->assertEqualsWithDelta(4.8, $result['estimates']['many'], 0.1);
        $this->assertSame(['few', 'many', 'mid'], array_keys($result['estimates']));
    }

    public function test_empirical_bayes_uses_the_fallback_weight_with_one_group(): void
    {
        $result = EmpiricalBayes::shrink(['only' => ['n' => 5, 'sum' => 20.0, 'sum_squares' => 82.0]]);

        $this->assertFalse($result['estimated']);
        $this->assertSame(EmpiricalBayes::FALLBACK_WEIGHT, $result['prior_weight']);
        $this->assertEqualsWithDelta(4.0, $result['estimates']['only'], 1e-12);
        $this->assertSame([], EmpiricalBayes::shrink([])['estimates']);
    }

    public function test_the_wilson_lower_bound_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(0.4902, EmpiricalBayes::wilsonLower(8, 10), 0.0005);
        $this->assertEqualsWithDelta(0.7112, EmpiricalBayes::wilsonLower(80, 100), 0.0005);
        $this->assertNull(EmpiricalBayes::wilsonLower(0, 0));
    }

    public function test_abc_classification_follows_cumulative_revenue_share(): void
    {
        $classes = ParetoClassifier::abc(['a' => 70.0, 'b' => 20.0, 'c' => 5.0, 'd' => 3.0, 'e' => 2.0, 'f' => 0.0]);

        $this->assertSame(['a' => 'A', 'b' => 'A', 'c' => 'B', 'd' => 'C', 'e' => 'C', 'f' => 'C'], $classes);
    }

    public function test_xyz_classification_follows_demand_variability(): void
    {
        $this->assertSame('X', ParetoClassifier::xyz([10.0, 10.0, 10.0, 10.0]));
        $this->assertSame('Y', ParetoClassifier::xyz([5.0, 15.0, 5.0, 15.0]));
        $this->assertSame('Z', ParetoClassifier::xyz([0.0, 20.0, 0.0, 20.0]));
        $this->assertSame('Z', ParetoClassifier::xyz([0.0, 0.0]));
        $this->assertSame('Z', ParetoClassifier::xyz([]));
    }

    public function test_log_odds_finds_the_words_that_separate_low_from_high_reviews(): void
    {
        $low = ['Slow checkout and rude staff', 'Very slow queue, rude cashier', 'rude clerk again, slow service'];
        $high = ['Friendly staff, great quality', 'Great quality and friendly service', 'friendly cashier, great prices'];

        $terms = LogOddsTerms::compare($low, $high);

        $this->assertContains('slow', array_column($terms['a'], 'term'));
        $this->assertContains('rude', array_column($terms['a'], 'term'));
        $this->assertContains('friendly', array_column($terms['b'], 'term'));
        $this->assertContains('great', array_column($terms['b'], 'term'));
        $this->assertNotContains('staff', array_column($terms['a'], 'term'), 'a word used by both groups is not a signal');
        $this->assertSame(['rude', 'staff', 'cashier'], LogOddsTerms::tokenise('The Rude STAFF and the cashier!'));
        $this->assertSame(['a' => [], 'b' => []], LogOddsTerms::compare([], $high));
    }
}
