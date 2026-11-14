<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AnalyticsReports\Services\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The only place that reads sales, orders, returns, stock, reviews, campaigns and complaints for the
 * dashboards, monthly reports, exports and forecasts. Sharing these queries is what makes the figures
 * reconcile: a number on a dashboard, in a monthly report and in a CSV file comes from the same query.
 *
 * Records of other modules are read with the query builder over their tables, never through their
 * classes. Every method takes the shop ids it may read; the callers decide which shops those are.
 * Periods are [start, end) in UTC; days are bucketed in the mall's time zone.
 */
class SourceData
{
    public const SIMULATED_PREFIX = 'SIM-';

    public const SIMULATED_REVIEWER_DOMAIN = '@sim.demo.test';

    /**
     * Completed purchases only: the recorded sales.
     *
     * @param  list<string>  $shopIds
     */
    public function sales(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return DB::table('purchases')->whereIn('purchases.shop_id', $shopIds)->where('purchases.status', 'completed')
            ->where('purchases.purchased_at', '>=', $start)->where('purchases.purchased_at', '<', $end);
    }

    /**
     * @param  list<string>  $shopIds
     * @return array<string, array{revenue: float, sales: int}> per shop id
     */
    public function salesByShop(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $this->sales($shopIds, $start, $end)->selectRaw('purchases.shop_id as shop, coalesce(sum(purchases.total_amount), 0) as revenue, count(*) as sales')->groupBy('purchases.shop_id')->get();

        return $rows->mapWithKeys(fn ($row): array => [(string) $row->shop => ['revenue' => round((float) $row->revenue, 2), 'sales' => (int) $row->sales]])->all();
    }

    /**
     * @param  list<string>  $shopIds
     * @return array{revenue: float, sales: int, units: int}
     */
    public function salesTotals(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = $this->sales($shopIds, $start, $end)->selectRaw('coalesce(sum(purchases.total_amount), 0) as revenue, count(*) as sales')->first();
        $units = $this->sales($shopIds, $start, $end)->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.purchase_id')->sum('purchase_items.quantity');

        return ['revenue' => round((float) $row->revenue, 2), 'sales' => (int) $row->sales, 'units' => (int) $units];
    }

    /**
     * Revenue per mall day for every day of the period (0 where nothing was sold), in time order.
     *
     * @param  list<string>  $shopIds
     * @return array<string, float>
     */
    public function dailyRevenue(array $shopIds, ReportPeriod $period): array
    {
        $days = $this->emptyDays($period);

        foreach ($this->sales($shopIds, $period->startsAt(), $period->endsAt())->get(['purchases.purchased_at', 'purchases.total_amount']) as $row) {
            $day = $this->localDay($row->purchased_at, $period->timezone);

            if (isset($days[$day])) {
                $days[$day] += (float) $row->total_amount;
            }
        }

        return array_map(fn (float $revenue): float => round($revenue, 2), $days);
    }

    /**
     * Revenue and units per listing, with units per full week of the period (weeks counted from its first day).
     *
     * @param  list<string>  $shopIds
     * @return array<string, array{revenue: float, units: int, weekly: list<float>}>
     */
    public function listingSales(array $shopIds, ReportPeriod $period): array
    {
        $weeks = intdiv($period->dayCount(), 7);
        $listings = [];

        $rows = $this->sales($shopIds, $period->startsAt(), $period->endsAt())
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.purchase_id')
            ->get(['purchase_items.shop_product_id', 'purchase_items.quantity', 'purchase_items.line_total_amount', 'purchases.purchased_at']);

        foreach ($rows as $row) {
            $key = (string) $row->shop_product_id;
            $listings[$key] ??= ['revenue' => 0.0, 'units' => 0, 'weekly' => array_fill(0, $weeks, 0.0)];
            $listings[$key]['revenue'] += (float) $row->line_total_amount;
            $listings[$key]['units'] += (int) $row->quantity;

            $week = intdiv((int) $period->from->diffInDays(CarbonImmutable::parse($this->localDay($row->purchased_at, $period->timezone), $period->timezone)), 7);

            if ($week < $weeks) {
                $listings[$key]['weekly'][$week] += (float) $row->quantity;
            }
        }

        return array_map(fn (array $l): array => ['revenue' => round($l['revenue'], 2)] + $l, $listings);
    }

    /**
     * @param  list<string>  $shopIds
     * @return array{placed: int, completed: int, reservations: int, by_status: array<string, int>}
     */
    public function orderCounts(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('shop_orders')->whereIn('shop_id', $shopIds)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('status, kind, count(*) as total')->groupBy('status', 'kind')->get();

        $byStatus = [];
        $reservations = 0;

        foreach ($rows as $row) {
            $byStatus[$row->status] = ($byStatus[$row->status] ?? 0) + (int) $row->total;
            $reservations += $row->kind === 'reservation' ? (int) $row->total : 0;
        }

        ksort($byStatus);

        return ['placed' => array_sum($byStatus), 'completed' => $byStatus['completed'] ?? 0, 'reservations' => $reservations, 'by_status' => $byStatus];
    }

    /**
     * @param  list<string>  $shopIds
     * @return array<string, array{placed: int, completed: int}> per shop id
     */
    public function orderCountsByShop(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('shop_orders')->whereIn('shop_id', $shopIds)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('shop_id, count(*) as placed, sum(case when status = \'completed\' then 1 else 0 end) as completed')->groupBy('shop_id')->get();

        return $rows->mapWithKeys(fn ($row): array => [(string) $row->shop_id => ['placed' => (int) $row->placed, 'completed' => (int) $row->completed]])->all();
    }

    /**
     * @param  list<string>  $shopIds
     * @return array{count: int, value: float}
     */
    public function completedReturns(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = DB::table('shop_returns')->whereIn('shop_id', $shopIds)->where('status', 'completed')->where('completed_at', '>=', $start)->where('completed_at', '<', $end)
            ->selectRaw('count(*) as total, coalesce(sum(value_amount), 0) as value')->first();

        return ['count' => (int) $row->total, 'value' => round((float) $row->value, 2)];
    }

    /**
     * Stock of the shop's active listings, as of now. Listings that do not track stock are not included.
     *
     * @param  list<string>  $shopIds
     * @return list<array{listing: string, on_hand: int, threshold: int}>
     */
    public function stock(array $shopIds): array
    {
        return DB::table('listing_stocks')->join('shop_products', 'shop_products.shop_product_id', '=', 'listing_stocks.shop_product_id')
            ->whereIn('listing_stocks.shop_id', $shopIds)->where('shop_products.status', 'active')
            ->get(['listing_stocks.shop_product_id', 'listing_stocks.on_hand', 'listing_stocks.low_stock_threshold'])
            ->map(fn ($row): array => ['listing' => (string) $row->shop_product_id, 'on_hand' => (int) $row->on_hand, 'threshold' => (int) $row->low_stock_threshold])->all();
    }

    /**
     * Published reviews created in [start, end).
     *
     * @param  list<string>  $shopIds
     * @return list<array{shop: string, rating: int, body: string, at: CarbonImmutable}>
     */
    public function reviews(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('reviews')->whereIn('shop_id', $shopIds)->where('status', 'published')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->orderBy('created_at')->get(['shop_id', 'rating', 'title', 'body', 'created_at'])
            ->map(fn ($row): array => ['shop' => (string) $row->shop_id, 'rating' => (int) $row->rating, 'body' => trim(($row->title ?? '').' '.$row->body), 'at' => CarbonImmutable::parse($row->created_at, 'UTC')])->all();
    }

    /**
     * Redemptions that were not released, per promotion, with the value of the orders they were used on.
     *
     * @param  list<string>  $shopIds
     * @return list<array{promotion: string, title: string, redemptions: int, discount: float, order_value: float}>
     */
    public function promotions(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('promotion_redemptions as r')->join('shop_promotions as p', 'p.shop_promotion_id', '=', 'r.shop_promotion_id')
            ->join('shop_orders as o', 'o.shop_order_id', '=', 'r.shop_order_id')
            ->whereIn('p.shop_id', $shopIds)->whereNull('r.released_at')->where('r.created_at', '>=', $start)->where('r.created_at', '<', $end)
            ->groupBy('p.shop_promotion_id', 'p.title')->orderBy('p.title')
            ->selectRaw('p.shop_promotion_id as promotion, p.title, count(*) as redemptions, sum(r.discount_amount) as discount, sum(o.total_amount) as order_value')->get()
            ->map(fn ($row): array => ['promotion' => (string) $row->promotion, 'title' => (string) $row->title, 'redemptions' => (int) $row->redemptions, 'discount' => round((float) $row->discount, 2), 'order_value' => round((float) $row->order_value, 2)])->all();
    }

    /**
     * @param  list<string>  $shopIds
     * @return array{total: int, open: int}
     */
    public function supportComplaints(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('support_threads')->whereIn('shop_id', $shopIds)->where('kind', 'complaint')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['total' => (int) $rows->sum(), 'open' => (int) ($rows['open'] ?? 0) + (int) ($rows['answered'] ?? 0)];
    }

    /**
     * Complaints to mall management filed in [start, end).
     *
     * @return list<array{number: string, shop: ?string, category: string, status: string, filed: CarbonImmutable, closed: ?CarbonImmutable}>
     */
    public function complaints(string $mallId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('complaints')->where('mall_id', $mallId)->where('created_at', '>=', $start)->where('created_at', '<', $end)->orderBy('created_at')
            ->get(['complaint_number', 'shop_id', 'category', 'status', 'created_at', 'closed_at'])
            ->map(fn ($row): array => [
                'number' => (string) $row->complaint_number, 'shop' => $row->shop_id === null ? null : (string) $row->shop_id, 'category' => (string) $row->category, 'status' => (string) $row->status,
                'filed' => CarbonImmutable::parse($row->created_at, 'UTC'), 'closed' => $row->closed_at === null ? null : CarbonImmutable::parse($row->closed_at, 'UTC'),
            ])->all();
    }

    /**
     * How much of the data in the period is simulated demonstration data (marked by reference prefix or
     * reviewer address), so reports can say so instead of passing it off as real.
     *
     * @param  list<string>  $shopIds
     * @return array{sales: array{simulated: int, total: int}, orders: array{simulated: int, total: int}, reviews: array{simulated: int, total: int}, any: bool}
     */
    public function provenance(array $shopIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $prefix = self::SIMULATED_PREFIX.'%';

        $sales = $this->sales($shopIds, $start, $end)->selectRaw('count(*) as total, sum(case when purchases.external_receipt_ref like ? then 1 else 0 end) as simulated', [$prefix])->first();
        $orders = DB::table('shop_orders')->whereIn('shop_id', $shopIds)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('count(*) as total, sum(case when order_number like ? then 1 else 0 end) as simulated', [$prefix])->first();
        $reviews = DB::table('reviews')->join('users', 'users.user_id', '=', 'reviews.author_user_id')->whereIn('reviews.shop_id', $shopIds)->where('reviews.status', 'published')
            ->where('reviews.created_at', '>=', $start)->where('reviews.created_at', '<', $end)
            ->selectRaw('count(*) as total, sum(case when users.email like ? then 1 else 0 end) as simulated', ['%'.self::SIMULATED_REVIEWER_DOMAIN])->first();

        $result = [
            'sales' => ['simulated' => (int) $sales->simulated, 'total' => (int) $sales->total],
            'orders' => ['simulated' => (int) $orders->simulated, 'total' => (int) $orders->total],
            'reviews' => ['simulated' => (int) $reviews->simulated, 'total' => (int) $reviews->total],
        ];

        return $result + ['any' => $result['sales']['simulated'] + $result['orders']['simulated'] + $result['reviews']['simulated'] > 0];
    }

    /**
     * @return array<string, float> every day of the period at 0.0, in order
     */
    public function emptyDays(ReportPeriod $period): array
    {
        $days = [];

        for ($offset = 0; $offset < $period->dayCount(); $offset++) {
            $days[$period->from->addDays($offset)->format('Y-m-d')] = 0.0;
        }

        return $days;
    }

    public function localDay(mixed $timestamp, string $timezone): string
    {
        return CarbonImmutable::parse((string) $timestamp, 'UTC')->setTimezone($timezone)->format('Y-m-d');
    }
}
