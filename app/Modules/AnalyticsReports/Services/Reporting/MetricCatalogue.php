<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;

/**
 * The metric catalogue as rows for the documentation screens: every dashboard figure with its meaning,
 * source, formula, dimensions, refresh strategy and access policy, limited to what the reader may see.
 */
class MetricCatalogue
{
    /**
     * @param  string  $scope  ReportMetric::SHOP or ReportMetric::MALL
     * @return list<array{code: string, label: string, unit: string, sources: list<string>, formula: string, dimensions: string, refresh: string, algorithm: ?string, visibility: string, available: bool, unavailable_reason: ?string, version: int}>
     */
    public function rows(string $scope): array
    {
        $rows = [];

        foreach (ReportMetric::cases() as $metric) {
            if ($scope === ReportMetric::SHOP ? $metric->visibleToShop() : $metric->visibleToMall()) {
                $rows[] = [
                    'code' => $metric->value,
                    'label' => $metric->label(),
                    'unit' => $metric->unit(),
                    'sources' => $metric->sources(),
                    'formula' => $metric->formula(),
                    'dimensions' => $metric->dimensions(),
                    'refresh' => $metric->refresh(),
                    'algorithm' => $metric->algorithm(),
                    'visibility' => $metric->visibility(),
                    'available' => $metric->available(),
                    'unavailable_reason' => $metric->unavailableReason(),
                    'version' => ReportMetric::FORMULA_VERSION,
                ];
            }
        }

        foreach (SummaryMetric::cases() as $metric) {
            $shopMetric = in_array($metric, [SummaryMetric::ShopPageViews, SummaryMetric::ProductPageViews], true);

            if ($scope === ReportMetric::MALL || $shopMetric) {
                $rows[] = [
                    'code' => $metric->value,
                    'label' => $metric->label(),
                    'unit' => 'count',
                    'sources' => [$metric->sourceTable(), 'activity_daily_summaries'],
                    'formula' => $metric->formula(),
                    'dimensions' => 'Mall day (mall time zone), shop, product, search term, kind',
                    'refresh' => 'Rolled up from the raw events every hour; raw events are kept 90 days, the summaries are kept.',
                    'algorithm' => null,
                    'visibility' => $metric->visibility(),
                    'available' => true,
                    'unavailable_reason' => null,
                    'version' => SummaryMetric::FORMULA_VERSION,
                ];
            }
        }

        return $rows;
    }
}
