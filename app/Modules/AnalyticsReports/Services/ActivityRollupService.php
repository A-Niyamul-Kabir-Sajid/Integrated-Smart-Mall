<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Modules\AnalyticsReports\Enums\SummaryMetric;
use App\Modules\AnalyticsReports\Models\ActivitySummary;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns the raw events of one mall day (in the mall's time zone) into summary rows. A day is
 * recomputed from scratch inside one transaction, so running it twice gives the same rows; the
 * rows record the formula version and the moment the events were read.
 */
class ActivityRollupService
{
    /**
     * @return int number of summary rows written for the day
     */
    public function rollupDay(Mall $mall, CarbonImmutable $day): int
    {
        $day = $day->setTimezone($mall->timezone)->startOfDay();
        $start = $day->utc();
        $end = $day->addDay()->startOfDay()->utc();
        $cutoff = now();

        return DB::transaction(function () use ($mall, $day, $start, $end, $cutoff): int {
            ActivitySummary::query()->where('mall_id', $mall->getKey())->where('day', $day->format('Y-m-d'))->delete();

            $rows = [
                ...$this->searchTotals($mall, $start, $end),
                ...$this->searchBySubject($mall, $start, $end, 'term', SummaryMetric::SearchTerm, false),
                ...$this->searchBySubject($mall, $start, $end, 'term', SummaryMetric::SearchTermZeroResult, true),
                ...$this->searchBySubject($mall, $start, $end, 'category', SummaryMetric::SearchCategory, false),
                ...$this->searchBySubject($mall, $start, $end, 'floor', SummaryMetric::SearchFloor, false),
                ...$this->views($mall, $start, $end, 'shop'),
                ...$this->views($mall, $start, $end, 'product'),
            ];

            $base = [
                'mall_id' => $mall->getKey(),
                'day' => $day->format('Y-m-d'),
                'formula_version' => SummaryMetric::FORMULA_VERSION,
                'source_cutoff_at' => $cutoff,
            ];

            foreach (array_chunk($rows, 500) as $chunk) {
                ActivitySummary::query()->insert(array_map(fn (array $row): array => [...$base, ...$row], $chunk));
            }

            return count($rows);
        });
    }

    /**
     * Rolls up each day from $from to $to (both included, mall time zone).
     */
    public function rollupRange(Mall $mall, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $rows = 0;

        for ($day = $from->setTimezone($mall->timezone)->startOfDay(); $day->lte($to->setTimezone($mall->timezone)); $day = $day->addDay()) {
            $rows += $this->rollupDay($mall, $day);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchTotals(Mall $mall, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('activity_search_events')
            ->where('mall_id', $mall->getKey())->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw('kind, dataset_label, count(*) as total, sum(case when result_count = 0 then 1 else 0 end) as zero_results')
            ->groupBy('kind', 'dataset_label')->get()
            ->map(fn ($row): array => [
                'metric_code' => SummaryMetric::SearchTotal->value,
                'kind' => $row->kind,
                'subject_key' => '',
                'shop_id' => null,
                'value' => (int) $row->total,
                'secondary' => (int) $row->zero_results,
                'dataset_label' => $row->dataset_label,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchBySubject(Mall $mall, CarbonImmutable $start, CarbonImmutable $end, string $column, SummaryMetric $metric, bool $zeroResultsOnly): array
    {
        return DB::table('activity_search_events')
            ->where('mall_id', $mall->getKey())->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->whereNotNull($column)
            ->when($zeroResultsOnly, fn ($query) => $query->where('result_count', 0))
            ->selectRaw("kind, dataset_label, {$column} as subject, count(*) as total")
            ->groupBy('kind', 'dataset_label', $column)->get()
            ->map(fn ($row): array => [
                'metric_code' => $metric->value,
                'kind' => $row->kind,
                'subject_key' => $row->subject,
                'shop_id' => null,
                'value' => (int) $row->total,
                'secondary' => 0,
                'dataset_label' => $row->dataset_label,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function views(Mall $mall, CarbonImmutable $start, CarbonImmutable $end, string $kind): array
    {
        $column = $kind === 'shop' ? 'shop_id' : 'product_id';
        $metric = $kind === 'shop' ? SummaryMetric::ShopPageViews : SummaryMetric::ProductPageViews;

        return DB::table('activity_view_events')
            ->where('mall_id', $mall->getKey())->where('kind', $kind)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw("{$column} as subject, dataset_label, count(*) as total, count(distinct visitor_key) as visitors")
            ->groupBy($column, 'dataset_label')->get()
            ->map(fn ($row): array => [
                'metric_code' => $metric->value,
                'kind' => $kind,
                'subject_key' => $row->subject,
                'shop_id' => $kind === 'shop' ? $row->subject : null,
                'value' => (int) $row->total,
                'secondary' => (int) $row->visitors,
                'dataset_label' => $row->dataset_label,
            ])->all();
    }
}
