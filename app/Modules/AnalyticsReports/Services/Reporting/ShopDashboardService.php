<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Services\ActivityReportService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\SharedPlatform\Enums\Ability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * One shop's dashboard (Jira EPIC-12, decision D-017): recorded sales, orders and reservations, product
 * performance, inventory, customer feedback and campaigns. Everything is read for that one shop only and
 * only for its owner; the figures come from SourceData, the same queries the monthly report and the
 * exports use, so they reconcile. The result holds plain arrays, so it can be stored in a monthly report.
 */
class ShopDashboardService
{
    public function __construct(
        private readonly SourceData $source,
        private readonly SalesInsights $sales,
        private readonly FeedbackInsights $feedback,
        private readonly ActivityReportService $activity,
    ) {}

    /**
     * @return array{
     *     metrics: array<string, int|float|null>,
     *     period: array{from: string, to: string, timezone: string, days: int},
     *     daily_sales: array<string, float>,
     *     orders: array{placed: int, completed: int, reservations: int, by_status: array<string, int>},
     *     products: list<array<string, mixed>>,
     *     inventory: array{tracked: int, stock_out: list<string>, low: list<array{label: string, on_hand: int, threshold: int}>, slow_moving: list<string>},
     *     feedback: array<string, mixed>,
     *     campaigns: list<array<string, mixed>>,
     *     visitors: array{shop_views: int, shop_visitors: int, funnel: list<array<string, mixed>>},
     *     anomalies: array<string, mixed>,
     *     provenance: array<string, mixed>,
     *     source_cutoff: string
     * }
     */
    public function dashboard(User $owner, Shop $shop, ReportPeriod $period): array
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);

        $ids = [(string) $shop->getKey()];
        $start = $period->startsAt();
        $end = $period->endsAt();

        $totals = $this->source->salesTotals($ids, $start, $end);
        $daily = $this->source->dailyRevenue($ids, $period);
        $orders = $this->source->orderCounts($ids, $start, $end);
        $returns = $this->source->completedReturns($ids, $start, $end);
        $promotions = $this->source->promotions($ids, $start, $end);
        $support = $this->source->supportComplaints($ids, $start, $end);

        $listings = ShopProduct::query()->where('shop_id', $shop->getKey())->with('variant.product')->get();
        $labels = $listings->where('status', 'active')->mapWithKeys(fn (ShopProduct $l): array => [(string) $l->getKey() => $this->label($l)])->all();
        $sold = $this->source->listingSales($ids, $period);

        foreach (array_diff_key($sold, $labels) as $key => $unused) {
            $listing = $listings->firstWhere('shop_product_id', $key);
            $labels[$key] = $listing === null ? __('Removed listing') : $this->label($listing);
        }

        $stock = collect($this->source->stock($ids))->mapWithKeys(fn (array $row): array => [$row['listing'] => $row['on_hand']])->all();
        $products = $this->sales->productRows($sold, $labels, $stock, $period->dayCount());
        $inventory = $this->inventory($labels, $this->source->stock($ids), $products);

        $visits = $this->activity->shopAnalytics($owner, $shop, $period, DatasetLabel::Real);
        $reviews = $this->source->reviews($ids, $start, $end);
        $feedback = $this->feedback->summary($reviews);
        $feedback['trend'] = $this->feedback->trend($this->source->reviews($ids, $end->subDays(FeedbackInsights::TREND_WINDOW_DAYS), $end));
        $feedback['support_complaints'] = $support;

        return [
            'metrics' => [
                ReportMetric::SalesRevenue->value => $totals['revenue'],
                ReportMetric::SalesCount->value => $totals['sales'],
                ReportMetric::AverageSaleValue->value => $totals['sales'] > 0 ? round($totals['revenue'] / $totals['sales'], 2) : null,
                ReportMetric::UnitsSold->value => $totals['units'],
                ReportMetric::OrdersPlaced->value => $orders['placed'],
                ReportMetric::OrdersCompleted->value => $orders['completed'],
                ReportMetric::ReservationsPlaced->value => $orders['reservations'],
                ReportMetric::OrderCompletionRate->value => $orders['placed'] > 0 ? round($orders['completed'] / $orders['placed'] * 100, 1) : null,
                ReportMetric::ReturnsCompleted->value => $returns['count'],
                ReportMetric::ReturnsValue->value => $returns['value'],
                ReportMetric::StockUnitsOnHand->value => array_sum($stock),
                ReportMetric::StockOutListings->value => count($inventory['stock_out']),
                ReportMetric::LowStockListings->value => count($inventory['low']),
                ReportMetric::ReviewsPublished->value => $feedback['count'],
                ReportMetric::RatingAverage->value => $feedback['average'],
                ReportMetric::RatingTrend->value => $feedback['trend']['slope_per_30_days'],
                ReportMetric::SupportComplaints->value => $support['total'],
                ReportMetric::CouponRedemptions->value => array_sum(array_column($promotions, 'redemptions')),
                ReportMetric::DiscountGiven->value => round(array_sum(array_column($promotions, 'discount')), 2),
            ],
            'period' => ['from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d'), 'timezone' => $period->timezone, 'days' => $period->dayCount()],
            'daily_sales' => $daily,
            'orders' => $orders,
            'products' => $products,
            'inventory' => $inventory,
            'feedback' => $feedback,
            'campaigns' => $promotions,
            'visitors' => ['shop_views' => $visits['shop_views'], 'shop_visitors' => $visits['shop_visitors'], 'funnel' => $visits['funnel']->all()],
            'anomalies' => $this->sales->anomalies($daily, CarbonImmutable::now($period->timezone)->format('Y-m-d')),
            'provenance' => $this->source->provenance($ids, $start, $end),
            'source_cutoff' => now()->toIso8601String(),
        ];
    }

    private function label(ShopProduct $listing): string
    {
        return trim(($listing->variant?->product?->name ?? __('Removed product')).' · '.($listing->variant?->label ?? ''), ' ·');
    }

    /**
     * @param  array<string, string>  $labels
     * @param  list<array{listing: string, on_hand: int, threshold: int}>  $stock
     * @param  list<array<string, mixed>>  $products
     * @return array{tracked: int, stock_out: list<string>, low: list<array{label: string, on_hand: int, threshold: int}>, slow_moving: list<string>}
     */
    private function inventory(array $labels, array $stock, array $products): array
    {
        $out = $low = [];

        foreach ($stock as $row) {
            $label = $labels[$row['listing']] ?? __('Removed listing');

            if ($row['on_hand'] === 0) {
                $out[] = $label;
            } elseif ($row['on_hand'] <= $row['threshold']) {
                $low[] = ['label' => $label, 'on_hand' => $row['on_hand'], 'threshold' => $row['threshold']];
            }
        }

        sort($out);
        usort($low, fn (array $a, array $b): int => [$a['on_hand'], $a['label']] <=> [$b['on_hand'], $b['label']]);

        $slow = array_values(array_map(fn (array $p): string => $p['label'], array_filter($products, fn (array $p): bool => $p['slow_moving'])));
        sort($slow);

        return ['tracked' => count($stock), 'stock_out' => $out, 'low' => $low, 'slow_moving' => $slow];
    }
}
