<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Algorithms\ForecastMetrics;
use App\Modules\AnalyticsReports\Algorithms\HoltWinters;
use App\Modules\AnalyticsReports\Algorithms\RidgeRegression;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * The sales forecast prototype (Jira EPIC-12, decision D-017). It is labelled an estimate everywhere.
 *
 * Target: recorded sales revenue per mall day (completed purchases only) of one shop, or of the whole mall.
 * Data: the last 180 complete days before today, starting at the first day with a sale.
 * Method, in the order it is carried out:
 *  1. The last 14 days are set aside and never used to choose or fit anything (the hold-out).
 *  2. Four candidates are fitted on the rest: two baselines (repeat last week; mean of the same weekday over
 *     the last four weeks) and two models (ridge regression on a weekly trend and weekday indicators, with the
 *     penalty chosen from 0, 1, 10, 50; Holt–Winters damped additive smoothing).
 *  3. Rolling-origin cross-validation inside the training part (up to three 14-day folds, each fitted only on
 *     days before it) gives every candidate a mean absolute error; the candidate with the lowest wins.
 *  4. All four are then scored once on the hold-out (MAE, RMSE, sMAPE, MASE, bias), and the verdict compares the
 *     winner with the best baseline there.
 *  5. The winner is refitted on all days and forecasts the next 14 days with an 80% interval: the exact
 *     regression prediction interval for ordinary least squares, otherwise plus or minus 1.28 times the
 *     hold-out RMSE (an empirical interval).
 * Nothing is random, so the same history always gives the same numbers (the run id).
 */
class ForecastService
{
    public const HORIZON = 14;

    public const HOLDOUT = 14;

    public const HISTORY_DAYS = 180;

    public const MIN_DAYS = 56;

    public const MIN_SALES_DAYS = 21;

    public const INTERVAL = 0.8;

    private const LAMBDAS = [0.0, 1.0, 10.0, 50.0];

    private const MAX_FOLDS = 3;

    public function __construct(private readonly SourceData $source) {}

    /**
     * @return array<string, mixed>
     */
    public function forShop(User $owner, Shop $shop, ?CarbonImmutable $today = null): array
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);

        return $this->forecast([(string) $shop->getKey()], $shop->loadMissing('mall')->mall, 'shop', (string) $shop->getKey(), $today);
    }

    /**
     * @return array<string, mixed>
     */
    public function forMall(User $administrator, Mall $mall, ?CarbonImmutable $today = null): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $ids = DB::table('shops')->where('mall_id', $mall->getKey())->where('status', '!=', 'draft')->pluck('shop_id')->map(fn ($id): string => (string) $id)->all();

        return $this->forecast($ids, $mall, 'mall', (string) $mall->getKey(), $today);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    private function forecast(array $ids, Mall $mall, string $scope, string $subject, ?CarbonImmutable $today): array
    {
        $today = ($today ?? CarbonImmutable::now($mall->timezone))->setTimezone($mall->timezone)->startOfDay();
        $last = $today->subDay();
        $period = ReportPeriod::fromInput($mall, $last->subDays(self::HISTORY_DAYS - 1)->format('Y-m-d'), $last->format('Y-m-d'));
        $daily = $this->trimLeadingZeros($this->source->dailyRevenue($ids, $period));
        $salesDays = count(array_filter($daily, fn (float $revenue): bool => $revenue > 0));
        $provenance = $this->source->provenance($ids, $period->startsAt(), $period->endsAt());

        $base = [
            'target' => __('Recorded sales revenue per day (BDT)'),
            'scope' => $scope,
            'horizon' => self::HORIZON,
            'holdout_days' => self::HOLDOUT,
            'interval_level' => self::INTERVAL,
            'history' => ['days' => count($daily), 'sales_days' => $salesDays, 'from' => array_key_first($daily), 'to' => array_key_last($daily), 'revenue' => round(array_sum($daily), 2), 'window_days' => self::HISTORY_DAYS],
            'provenance' => $provenance['sales'],
            'series' => $daily,
            'source_cutoff' => now()->toIso8601String(),
        ];

        if (count($daily) < self::MIN_DAYS || $salesDays < self::MIN_SALES_DAYS) {
            return $base + ['status' => 'insufficient', 'reason' => __('A forecast needs at least :days days of history from the first sale and at least :sales days with a sale; this history has :have days, :with with a sale.', ['days' => self::MIN_DAYS, 'sales' => self::MIN_SALES_DAYS, 'have' => count($daily), 'with' => $salesDays]), 'limitations' => $this->limitations($base['history'], $provenance['sales'], null)];
        }

        $y = array_values($daily);
        $n = count($y);
        $startWeekday = CarbonImmutable::parse((string) array_key_first($daily))->dayOfWeekIso - 1;
        $trainEnd = $n - self::HOLDOUT;
        $train = array_slice($y, 0, $trainEnd);
        $holdout = array_slice($y, $trainEnd);

        $folds = max(1, min(self::MAX_FOLDS, intdiv($trainEnd - 28, self::HOLDOUT)));
        $lambda = $this->chooseLambda($train, $startWeekday, $folds);
        $candidates = $this->candidates($lambda);

        $results = [];

        foreach ($candidates as $code => $candidate) {
            $cv = $this->crossValidate($candidate['predict'], $train, $startWeekday, $folds);
            $predicted = $candidate['predict']($train, $startWeekday, self::HOLDOUT);

            $results[$code] = [
                'code' => $code,
                'label' => $candidate['label'],
                'baseline' => $candidate['baseline'],
                'cv_mae' => round($cv, 2),
                'holdout' => [
                    'mae' => round(ForecastMetrics::mae($holdout, $predicted), 2),
                    'rmse' => round(ForecastMetrics::rmse($holdout, $predicted), 2),
                    'smape' => round(ForecastMetrics::smape($holdout, $predicted), 1),
                    'mase' => ($mase = ForecastMetrics::mase($holdout, $predicted, $train, 7)) === null ? null : round($mase, 2),
                    'bias' => round(ForecastMetrics::bias($holdout, $predicted), 2),
                ],
                'selected' => false,
            ];
        }

        $selected = array_key_first(array_filter($results, fn (array $r): bool => $r['cv_mae'] === min(array_column($results, 'cv_mae'))));
        $results[$selected]['selected'] = true;
        $bestBaseline = min(array_map(fn (array $r): float => $r['holdout']['mae'], array_filter($results, fn (array $r): bool => $r['baseline'])));
        $beats = ! $results[$selected]['baseline'] && $results[$selected]['holdout']['mae'] < $bestBaseline;

        $point = $candidates[$selected]['predict']($y, $startWeekday, self::HORIZON);
        $naive = $candidates['seasonal_naive']['predict']($y, $startWeekday, self::HORIZON);
        $rows = $this->forecastRows($selected, $y, $startWeekday, $lambda, $point, $naive, (string) array_key_last($daily), $results[$selected]['holdout']['rmse']);
        $regression = $this->regressionSummary($y, $startWeekday);

        return $base + [
            'status' => 'ok',
            'reason' => null,
            'run_id' => substr(sha1(json_encode([$scope, $subject, $daily, 1], JSON_THROW_ON_ERROR)), 0, 12),
            'training_cutoff' => (string) array_key_last($daily),
            'ridge_lambda' => $lambda,
            'cv_folds' => $folds,
            'models' => array_values($results),
            'selected' => $selected,
            'selected_label' => $results[$selected]['label'],
            'beats_baseline' => $beats,
            'best_baseline_mae' => $bestBaseline,
            'verdict' => $this->verdict($results[$selected], $bestBaseline, $beats),
            'forecast' => $rows,
            'forecast_total' => round(array_sum(array_column($rows, 'point')), 2),
            'regression' => $regression,
            'limitations' => $this->limitations($base['history'], $provenance['sales'], $regression),
        ];
    }

    /**
     * @return array<string, array{label: string, baseline: bool, predict: callable(list<float>, int, int): list<float>}>
     */
    private function candidates(float $lambda): array
    {
        return [
            'seasonal_naive' => ['label' => __('Baseline: repeat last week'), 'baseline' => true, 'predict' => function (array $train, int $startWeekday, int $h): array {
                $n = count($train);

                return array_map(fn (int $k): float => $train[$n - 7 + ($k % 7)], range(0, $h - 1));
            }],
            'weekday_mean' => ['label' => __('Baseline: mean of the same weekday over four weeks'), 'baseline' => true, 'predict' => function (array $train, int $startWeekday, int $h): array {
                $n = count($train);
                $recent = array_slice($train, max(0, $n - 28), null, true);

                return array_map(function (int $k) use ($recent, $n): float {
                    $values = array_filter($recent, fn (int $i): bool => $i % 7 === ($n + $k) % 7, ARRAY_FILTER_USE_KEY);

                    return $values === [] ? 0.0 : array_sum($values) / count($values);
                }, range(0, $h - 1));
            }],
            'regression' => ['label' => __('Ridge regression (weekly trend and weekday effects)'), 'baseline' => false, 'predict' => function (array $train, int $startWeekday, int $h) use ($lambda): array {
                $model = $this->fitRegression($train, $startWeekday, $lambda);
                $n = count($train);

                return array_map(fn (int $k): float => max(0.0, $model->predict($this->features($n + $k, $startWeekday))), range(0, $h - 1));
            }],
            'holt_winters' => ['label' => __('Holt–Winters smoothing (damped trend, weekly season)'), 'baseline' => false, 'predict' => fn (array $train, int $startWeekday, int $h): array => array_map(fn (float $v): float => max(0.0, $v), HoltWinters::fit($train, 7)->forecast($h))],
        ];
    }

    /**
     * Chooses the ridge penalty by rolling-origin cross-validation on the training days only.
     *
     * @param  list<float>  $train
     */
    private function chooseLambda(array $train, int $startWeekday, int $folds): float
    {
        $best = self::LAMBDAS[0];
        $bestError = INF;

        foreach (self::LAMBDAS as $lambda) {
            $predict = function (array $part, int $weekday, int $h) use ($lambda): array {
                $model = $this->fitRegression($part, $weekday, $lambda);

                return array_map(fn (int $k): float => max(0.0, $model->predict($this->features(count($part) + $k, $weekday))), range(0, $h - 1));
            };
            $error = $this->crossValidate($predict, $train, $startWeekday, $folds);

            if ($error < $bestError - 1e-9) {
                $best = $lambda;
                $bestError = $error;
            }
        }

        return $best;
    }

    /**
     * Mean absolute error over the folds; each fold is predicted from the days before it only.
     *
     * @param  callable(list<float>, int, int): list<float>  $predict
     * @param  list<float>  $train
     */
    private function crossValidate(callable $predict, array $train, int $startWeekday, int $folds): float
    {
        $errors = [];
        $n = count($train);

        for ($fold = $folds; $fold >= 1; $fold--) {
            $origin = $n - $fold * self::HOLDOUT;
            $actual = array_slice($train, $origin, self::HOLDOUT);
            $errors[] = ForecastMetrics::mae($actual, $predict(array_slice($train, 0, $origin), $startWeekday, self::HOLDOUT));
        }

        return array_sum($errors) / count($errors);
    }

    /**
     * @param  list<float>  $y
     */
    private function fitRegression(array $y, int $startWeekday, float $lambda): RidgeRegression
    {
        return RidgeRegression::fit(array_map(fn (int $i): array => $this->features($i, $startWeekday), array_keys($y)), $y, $lambda);
    }

    /**
     * A weekly trend (index in weeks) and indicators for Tuesday to Sunday (Monday is the reference).
     *
     * @return list<float>
     */
    private function features(int $index, int $startWeekday): array
    {
        $weekday = ($startWeekday + $index) % 7;

        return [$index / 7, ...array_map(fn (int $d): float => $weekday === $d ? 1.0 : 0.0, range(1, 6))];
    }

    /**
     * @param  list<float>  $point
     * @param  list<float>  $naive
     * @return list<array{day: string, point: float, low: float, high: float, baseline: float}>
     */
    private function forecastRows(string $selected, array $y, int $startWeekday, float $lambda, array $point, array $naive, string $lastDay, float $holdoutRmse): array
    {
        $rows = [];
        $regression = null;

        if ($selected === 'regression') {
            $regression = $this->fitRegression($y, $startWeekday, $lambda);
        }

        foreach ($point as $k => $value) {
            if ($regression !== null && $regression->degreesOfFreedom() > 0) {
                [$low, $high] = $regression->predictionInterval($this->features(count($y) + $k, $startWeekday), self::INTERVAL);
            } else {
                $low = $value - 1.2816 * $holdoutRmse;
                $high = $value + 1.2816 * $holdoutRmse;
            }

            $rows[] = [
                'day' => CarbonImmutable::parse($lastDay)->addDays($k + 1)->format('Y-m-d'),
                'point' => round($value, 2),
                'low' => round(max(0.0, $low), 2),
                'high' => round(max($value, $high), 2),
                'baseline' => round($naive[$k], 2),
            ];
        }

        return $rows;
    }

    /**
     * What ordinary least squares says about the history: the weekly trend and the weekday pattern, with tests.
     *
     * @param  list<float>  $y
     * @return array{observations: int, trend_per_week: float, trend_p: ?float, r_squared: float, adjusted_r_squared: ?float, durbin_watson: ?float, weekday_effects: list<array{weekday: string, effect: float, p: ?float}>}|null
     */
    private function regressionSummary(array $y, int $startWeekday): ?array
    {
        try {
            $model = $this->fitRegression($y, $startWeekday, 0.0);
        } catch (Throwable) {
            return null;
        }

        $fit = $model->coefficients();
        $p = $model->pValues();
        $names = [1 => __('Tuesday'), 2 => __('Wednesday'), 3 => __('Thursday'), 4 => __('Friday'), 5 => __('Saturday'), 6 => __('Sunday')];
        $effects = [];

        foreach ($names as $d => $name) {
            $effects[] = ['weekday' => (string) $name, 'effect' => round($fit['coefficients'][$d], 2), 'p' => isset($p[$d]) ? round($p[$d], 4) : null];
        }

        return [
            'observations' => $model->observations,
            'trend_per_week' => round($fit['coefficients'][0], 2),
            'trend_p' => isset($p[0]) ? round($p[0], 4) : null,
            'r_squared' => round($model->rSquared(), 3),
            'adjusted_r_squared' => ($adjusted = $model->adjustedRSquared()) === null ? null : round($adjusted, 3),
            'durbin_watson' => ($dw = $model->durbinWatson()) === null ? null : round($dw, 2),
            'weekday_effects' => $effects,
        ];
    }

    /**
     * @param  array{label: string, holdout: array{mae: float}}  $selected
     */
    private function verdict(array $selected, float $bestBaseline, bool $beats): string
    {
        if ($beats) {
            return __('On the held-out last :days days the chosen method (:label) had an average error of BDT :mae against BDT :base for the best simple baseline, :percent% lower. That is encouraging, not proof: it is one short test window.', [
                'days' => self::HOLDOUT, 'label' => $selected['label'], 'mae' => number_format($selected['holdout']['mae'], 2), 'base' => number_format($bestBaseline, 2),
                'percent' => number_format(($bestBaseline - $selected['holdout']['mae']) / $bestBaseline * 100, 0),
            ]);
        }

        return __('The chosen method (:label) did not beat the best simple baseline on the held-out last :days days (average error BDT :mae against BDT :base). Treat this forecast as no better than repeating recent weeks.', [
            'label' => $selected['label'], 'days' => self::HOLDOUT, 'mae' => number_format($selected['holdout']['mae'], 2), 'base' => number_format($bestBaseline, 2),
        ]);
    }

    /**
     * @param  array{days: int, sales_days: int}  $history
     * @param  array{simulated: int, total: int}  $sales
     * @param  array<string, mixed>|null  $regression
     * @return list<string>
     */
    private function limitations(array $history, array $sales, ?array $regression): array
    {
        $notes = [
            __('An estimate from past recorded sales only. It knows nothing about holidays, promotions, new stock, weather or the market, and is not footfall.'),
            __('The history is :days days (:sales with a sale); a forecast from so little data cannot see yearly seasons.', ['days' => $history['days'], 'sales' => $history['sales_days']]),
            __('The error figures come from one hold-out window of :days days; a different window could give a different winner.', ['days' => self::HOLDOUT]),
            __('Intervals assume errors that are independent and spread evenly; real sales are lumpier, so treat the interval as a rough guide.'),
        ];

        if ($sales['simulated'] > 0) {
            $notes[] = __(':simulated of the :total recorded sales in this history are simulated demonstration records, so this forecast demonstrates the method, not the real shop.', ['simulated' => $sales['simulated'], 'total' => $sales['total']]);
        }

        if ($regression !== null && $regression['durbin_watson'] !== null && ($regression['durbin_watson'] < 1.5 || $regression['durbin_watson'] > 2.5)) {
            $notes[] = __('The regression residuals are correlated from day to day (Durbin–Watson :dw), so its p-values and intervals are optimistic.', ['dw' => $regression['durbin_watson']]);
        }

        return $notes;
    }

    /**
     * @param  array<string, float>  $daily
     * @return array<string, float>
     */
    private function trimLeadingZeros(array $daily): array
    {
        foreach ($daily as $day => $revenue) {
            if ($revenue > 0) {
                return array_slice($daily, array_search($day, array_keys($daily), true), null, true);
            }
        }

        return [];
    }
}
