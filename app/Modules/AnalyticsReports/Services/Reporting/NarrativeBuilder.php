<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use Carbon\CarbonImmutable;

/**
 * Drafts the sentences of a monthly report from the computed figures and from nothing else: every
 * sentence is a fixed template filled with numbers of the dashboard payload, and lists the metric codes it
 * rests on, so a reader can check each claim. No free text is generated, nothing is inferred beyond the
 * figures, and a missing figure produces no sentence rather than a guess.
 */
class NarrativeBuilder
{
    /**
     * @param  array<string, mixed>  $payload  a shop report payload (dashboard, previous, month, partial, subject)
     * @return list<array{text: string, metrics: list<string>}>
     */
    public function shop(array $payload): array
    {
        $d = $payload['dashboard'];
        $m = $d['metrics'];
        $lines = array_merge($this->salesLines($payload, $payload['subject']['name']), []);

        if ($m['orders_placed'] > 0) {
            $lines[] = $this->line(__(':placed orders were placed (:reservations reservations); :completed (:rate%) were completed.', ['placed' => $m['orders_placed'], 'reservations' => $m['reservations_placed'], 'completed' => $m['orders_completed'], 'rate' => $this->number($m['order_completion_rate'], 1)]), ['orders_placed', 'reservations_placed', 'orders_completed', 'order_completion_rate']);
        }

        $top = $d['products'][0] ?? null;

        if ($top !== null && $top['revenue'] > 0) {
            $lines[] = $this->line(__('The top product by revenue was :label with BDT :revenue (:share% of recorded sales, class :class).', ['label' => $top['label'], 'revenue' => $this->money($top['revenue']), 'share' => $this->number($top['share'], 1), 'class' => $top['abc'].($top['xyz'] ?? '')]), ['sales_revenue', 'product_classes']);
        }

        if ($d['inventory']['stock_out'] !== []) {
            $lines[] = $this->line(__(':count active listings are out of stock: :names.', ['count' => count($d['inventory']['stock_out']), 'names' => $this->names($d['inventory']['stock_out'])]), ['stock_out_listings']);
        }

        if ($m['reviews_published'] > 0) {
            $lines[] = $this->line(__(':count reviews were published with an average rating of :average.', ['count' => $m['reviews_published'], 'average' => $this->number($m['rating_average'], 2)]), ['reviews_published', 'rating_average']);
        }

        $trend = $d['feedback']['trend'];

        if ($trend['available'] && $trend['significant']) {
            $lines[] = $this->line(__('Ratings are moving :direction by :slope stars per 30 days (p :p, :n reviews in the last 120 days).', ['direction' => $trend['direction'] === 'down' ? __('down') : __('up'), 'slope' => $this->number(abs($trend['slope_per_30_days']), 2), 'p' => FeedbackInsights::formatP($trend['p_value']), 'n' => $trend['reviews']]), ['rating_trend']);
        }

        if ($d['anomalies']['days'] !== []) {
            $lines[] = $this->line(__('Unusual sales days: :days.', ['days' => implode(', ', array_map(fn (array $a): string => $a['day'].' ('.($a['direction'] === 'high' ? __('high') : __('low')).')', $d['anomalies']['days']))]), ['sales_anomalies']);
        }

        if ($d['visitors']['shop_views'] > 0) {
            $lines[] = $this->line(__('The shop page was opened :views times by :visitors visitor-days of real visitors (a page view is not a visit to the mall).', ['views' => $d['visitors']['shop_views'], 'visitors' => $d['visitors']['shop_visitors']]), ['shop_page_views', 'product_page_views']);
        }

        if ($m['coupon_redemptions'] > 0) {
            $lines[] = $this->line(__(':count promotion redemptions gave BDT :discount in discounts.', ['count' => $m['coupon_redemptions'], 'discount' => $this->money($m['discount_given'])]), ['coupon_redemptions', 'discount_given']);
        }

        return array_merge($lines, $this->closing($payload));
    }

    /**
     * @param  array<string, mixed>  $payload  a mall report payload
     * @return list<array{text: string, metrics: list<string>}>
     */
    public function mall(array $payload): array
    {
        $d = $payload['dashboard'];
        $m = $d['metrics'];
        $lines = $this->salesLines($payload, $payload['subject']['name']);

        if ($m['sales_count'] > 0) {
            $lines[] = $this->line(__('Sales were recorded at :with of :active active shops.', ['with' => $m['shops_with_sales'], 'active' => $m['active_shops']]), ['shops_with_sales', 'active_shops']);
        }

        $top = $d['tenants'][0] ?? null;

        if ($top !== null && $top['revenue'] > 0) {
            $lines[] = $this->line(__('The top shop by recorded sales was :name with BDT :revenue (:share% of the mall total).', ['name' => $top['name'], 'revenue' => $this->money($top['revenue']), 'share' => $this->number($top['revenue_share'], 1)]), ['sales_revenue']);
        }

        if ($m['complaints_filed'] > 0) {
            $category = array_key_first($d['complaints']['by_category']);
            $lines[] = $this->line(__(':filed complaints were filed; :resolved resolved and :open still open. The most common category was :category.', ['filed' => $m['complaints_filed'], 'resolved' => $m['complaints_resolved'], 'open' => $m['complaints_unresolved'], 'category' => str_replace('_', ' ', (string) $category)]).($m['complaint_resolution_hours'] === null ? '' : ' '.__('The median time to resolve was :hours hours.', ['hours' => $this->number($m['complaint_resolution_hours'], 1)])), ['complaints_filed', 'complaints_resolved', 'complaints_unresolved', 'complaint_resolution_hours']);
        }

        if ($m['reviews_published'] > 0) {
            $lines[] = $this->line(__(':count reviews were published across the mall with an average rating of :average.', ['count' => $m['reviews_published'], 'average' => $this->number($m['rating_average'], 2)]), ['reviews_published', 'rating_average']);
        }

        if ($d['search']['searches'] > 0) {
            $unmet = $d['search']['unmet'][0] ?? null;
            $lines[] = $this->line(__(':searches searches were recorded, :none of them found nothing.', ['searches' => $d['search']['searches'], 'none' => $d['search']['zero_results']]).($unmet === null ? '' : ' '.__('The most searched term with no result was ":term" (:count times).', ['term' => $unmet['term'], 'count' => $unmet['zero_results']])), ['search_total', 'search_term_zero_result_count']);
        }

        if ($d['quality_flags'] !== []) {
            $lines[] = $this->line(__(':count service-quality flags were raised for: :names. Each is a prompt to look, with its evidence on the dashboard.', ['count' => count($d['quality_flags']), 'names' => $this->names(array_values(array_unique(array_column($d['quality_flags'], 'shop'))))]), ['quality_flags']);
        }

        if ($d['segments']['available']) {
            $lines[] = $this->line(__('The shops with activity fall into :k segments by recorded sales, orders, rating and views (silhouette :s).', ['k' => $d['segments']['k'], 's' => $this->number($d['segments']['silhouette'], 2)]), ['shop_segments']);
        }

        $lines[] = $this->line(__('Navigation demand is not measured yet because the maps module is not built; it would measure interest in destinations, not footfall.'), ['navigation_requests']);

        return array_merge($lines, $this->closing($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{text: string, metrics: list<string>}>
     */
    private function salesLines(array $payload, string $subject): array
    {
        $m = $payload['dashboard']['metrics'];
        $month = $this->monthName($payload['month']);
        $previous = $payload['previous']['metrics'] ?? null;
        $previousMonth = $this->monthName($payload['previous']['month'] ?? $payload['month']);

        if ($m['sales_count'] === 0) {
            return [$this->line(__('No sales were recorded for :subject in :month.', ['subject' => $subject, 'month' => $month]), ['sales_count'])];
        }

        $text = __(':subject recorded :count sales worth BDT :revenue in :month (average BDT :average per sale, :units units).', ['subject' => $subject, 'count' => $m['sales_count'], 'revenue' => $this->money($m['sales_revenue']), 'month' => $month, 'average' => $this->money($m['average_sale_value']), 'units' => $m['units_sold']]);

        if ($payload['partial']) {
            $text .= ' '.__('The month is not over, so these figures run up to :to.', ['to' => $payload['period']['to']]);
        } elseif ($previous !== null && $previous['sales_revenue'] > 0) {
            $change = ($m['sales_revenue'] - $previous['sales_revenue']) / $previous['sales_revenue'] * 100;
            $text .= ' '.__('That is :percent% :direction than the BDT :previous recorded in :month.', ['percent' => $this->number(abs($change), 1), 'direction' => $change >= 0 ? __('more') : __('less'), 'previous' => $this->money($previous['sales_revenue']), 'month' => $previousMonth]);
        } elseif ($previous !== null) {
            $text .= ' '.__('No sales were recorded in :month.', ['month' => $previousMonth]);
        }

        return [$this->line($text, ['sales_count', 'sales_revenue', 'average_sale_value', 'units_sold'])];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{text: string, metrics: list<string>}>
     */
    private function closing(array $payload): array
    {
        $sales = $payload['dashboard']['provenance']['sales'];

        if ($sales['simulated'] === 0) {
            return [];
        }

        return [$this->line(__('Note: :simulated of the :total recorded sales in this month are simulated demonstration records.', ['simulated' => $sales['simulated'], 'total' => $sales['total']]), ['sales_count'])];
    }

    /**
     * @param  list<string>  $metrics
     * @return array{text: string, metrics: list<string>}
     */
    private function line(string $text, array $metrics): array
    {
        return ['text' => $text, 'metrics' => $metrics];
    }

    /**
     * @param  list<string>  $names
     */
    private function names(array $names): string
    {
        $shown = array_slice($names, 0, 3);

        return implode(', ', $shown).(count($names) > 3 ? ' '.__('and :count more', ['count' => count($names) - 3]) : '');
    }

    private function monthName(string $month): string
    {
        return CarbonImmutable::parse($month.'-01')->format('F Y');
    }

    private function money(float|int|null $value): string
    {
        return number_format((float) $value, 2);
    }

    private function number(float|int|null $value, int $decimals): string
    {
        return number_format((float) $value, $decimals);
    }
}
