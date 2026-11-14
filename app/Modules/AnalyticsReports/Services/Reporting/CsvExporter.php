<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;

/**
 * Writes dashboards and monthly reports as CSV. The rows are taken from the same arrays the screens
 * show, so an export and its dashboard cannot disagree, and a shop export only ever holds that shop's
 * dashboard. Cells that start with = + - @ are prefixed with an apostrophe so a spreadsheet does not run
 * names a customer or shop typed as formulas.
 */
class CsvExporter
{
    /**
     * @param  array{scope: string, subject: string, label: string, period_from: string, period_to: string, timezone: string, generated: string, partial?: bool}  $meta
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, int|float|null>|null  $previous  metrics of the month before, for a monthly report
     */
    public function export(array $meta, array $dashboard, ?array $previous = null): string
    {
        $out = fopen('php://temp', 'r+');
        $put = function (array $row) use ($out): void {
            fputcsv($out, array_map($this->safe(...), $row), ',', '"', '');
        };

        $put([__('Report'), $meta['label']]);
        $put([__('Subject'), $meta['subject']]);
        $put([__('Scope'), $meta['scope']]);
        $put([__('Period'), $meta['period_from'].' to '.$meta['period_to'].' ('.$meta['timezone'].')']);
        $put([__('Metric catalogue version'), ReportMetric::FORMULA_VERSION]);
        $put([__('Data read at'), $meta['generated']]);

        if ($meta['partial'] ?? false) {
            $put([__('Note'), __('The month is not over: figures run up to the end date.')]);
        }

        $provenance = $dashboard['provenance'];

        if ($provenance['any']) {
            $put([__('Note'), __('Simulated demonstration records included: :sales of :sales_total sales, :orders of :orders_total orders, :reviews of :reviews_total reviews.', ['sales' => $provenance['sales']['simulated'], 'sales_total' => $provenance['sales']['total'], 'orders' => $provenance['orders']['simulated'], 'orders_total' => $provenance['orders']['total'], 'reviews' => $provenance['reviews']['simulated'], 'reviews_total' => $provenance['reviews']['total']])]);
        }

        $put([]);
        $put([__('Metrics')]);
        $put($previous === null ? ['code', 'metric', 'value', 'unit'] : ['code', 'metric', 'value', 'unit', 'previous month']);

        foreach ($dashboard['metrics'] as $code => $value) {
            $metric = ReportMetric::from($code);
            $row = [$code, $metric->label(), $this->figure($metric, $value), $metric->unit()];

            if ($previous !== null) {
                $row[] = $this->figure($metric, $previous[$code] ?? null);
            }

            $put($row);
        }

        $put([]);
        $put([__('Recorded sales revenue per day (BDT)')]);
        $put(['day', 'revenue']);

        foreach ($dashboard['daily_sales'] as $day => $revenue) {
            $put([$day, $this->money($revenue)]);
        }

        if ($meta['scope'] === ReportMetric::SHOP) {
            $this->shopSections($put, $dashboard);
        } else {
            $this->mallSections($put, $dashboard);
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return "\u{FEFF}".$csv;
    }

    /**
     * @param  callable(array<int, mixed>): void  $put
     * @param  array<string, mixed>  $d
     */
    private function shopSections(callable $put, array $d): void
    {
        $put([]);
        $put([__('Products')]);
        $put(['product', 'revenue', 'units', 'share %', 'ABC', 'XYZ', 'units in stock', 'days of cover', 'slow moving']);

        foreach ($d['products'] as $p) {
            $put([$p['label'], $this->money($p['revenue']), $p['units'], $this->plain($p['share']), $p['abc'], $p['xyz'] ?? '', $this->plain($p['on_hand']), $this->plain($p['days_of_cover']), $p['slow_moving'] ? 'yes' : 'no']);
        }

        $put([]);
        $put([__('Orders by status')]);
        $put(['status', 'orders']);

        foreach ($d['orders']['by_status'] as $status => $count) {
            $put([$status, $count]);
        }

        $put([]);
        $put([__('Promotions')]);
        $put(['promotion', 'redemptions', 'discount', 'value of orders']);

        foreach ($d['campaigns'] as $c) {
            $put([$c['title'], $c['redemptions'], $this->money($c['discount']), $this->money($c['order_value'])]);
        }

        $put([]);
        $put([__('Visitor activity (real visitors; a page view is not a visit to the mall)')]);
        $put(['shop_page_views', $d['visitors']['shop_views']]);
        $put(['visitor-days', $d['visitors']['shop_visitors']]);
        $put(['product', 'product page views', 'orders', 'recorded sales', 'orders per 100 views']);

        foreach ($d['visitors']['funnel'] as $f) {
            $put([$f['label'], $f['product_views'], $f['orders'], $f['sales'], $this->plain($f['conversion'])]);
        }

        $put([]);
        $put([__('Unusual sales days')]);
        $put(['day', 'revenue', 'expected for the weekday', 'modified z-score', 'direction']);

        foreach ($d['anomalies']['days'] as $a) {
            $put([$a['day'], $this->money($a['revenue']), $this->money($a['expected']), $this->plain($a['z']), $a['direction']]);
        }
    }

    /**
     * @param  callable(array<int, mixed>): void  $put
     * @param  array<string, mixed>  $d
     */
    private function mallSections(callable $put, array $d): void
    {
        $put([]);
        $put([__('Shops')]);
        $put(['shop', 'category', 'floor', 'revenue', 'share %', 'previous period revenue', 'change %', 'recorded sales', 'orders', 'orders completed', 'reviews', 'rating', 'adjusted rating', 'page views', 'complaints', 'segment']);

        foreach ($d['tenants'] as $t) {
            $put([$t['name'], $t['category'], $t['floor'] ?? '', $this->money($t['revenue']), $this->plain($t['revenue_share']), $this->money($t['previous_revenue']), $this->plain($t['change_percent']), $t['sales'], $t['orders'], $t['orders_completed'], $t['reviews'], $this->plain($t['rating']), $this->plain($t['rating_adjusted']), $t['views'], $t['complaints'], $t['segment'] ?? '']);
        }

        foreach (['categories' => __('Demand by shop category'), 'floors' => __('Demand by floor')] as $key => $title) {
            $put([]);
            $put([$title]);
            $put(['name', 'shops', 'revenue', 'share %', 'orders', 'page views', 'searches using the filter']);

            foreach ($d[$key] as $row) {
                $put([$row['name'], $row['shops'], $this->money($row['revenue']), $this->plain($row['share']), $row['orders'], $row['views'], $row['searches']]);
            }
        }

        $put([]);
        $put([__('Complaints by category')]);
        $put(['category', 'complaints']);

        foreach ($d['complaints']['by_category'] as $category => $count) {
            $put([$category, $count]);
        }

        $put([]);
        $put([__('Service-quality flags')]);
        $put(['shop', 'kind', 'evidence']);

        foreach ($d['quality_flags'] as $flag) {
            $put([$flag['shop'], $flag['kind'], $flag['evidence']]);
        }

        $put([]);
        $put([__('Navigation requests')]);
        $put([ReportMetric::NavigationRequests->unavailableReason()]);
        $put([]);
        $put([__('Search and page views (real visitors)')]);
        $put([SummaryMetric::SearchTotal->value, $d['search']['searches']]);
        $put(['searches with no result', $d['search']['zero_results']]);
        $put(['search terms with no result', 'times']);

        foreach ($d['search']['unmet'] as $term) {
            $put([$term['term'], $term['zero_results']]);
        }

        $put([SummaryMetric::ShopPageViews->value, $d['page_views']['shop_views']]);
    }

    /**
     * Money with two decimals; numbers stored as JSON may have lost their ".0", so the unit decides, not the PHP type.
     */
    private function money(mixed $value): string
    {
        return $value === null ? '' : number_format((float) $value, 2, '.', '');
    }

    /**
     * A number without trailing zeros (4.0 becomes 4, 183.330 becomes 183.33).
     */
    private function plain(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : (string) $value;
    }

    private function figure(ReportMetric $metric, mixed $value): string
    {
        return $metric->unit() === 'BDT' ? $this->money($value) : $this->plain($value);
    }

    private function safe(mixed $cell): string
    {
        $text = (string) $cell;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }
}
