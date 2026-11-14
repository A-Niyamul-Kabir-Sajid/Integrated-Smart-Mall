<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reads the daily summaries for the dashboards (Jira EPIC-14, decision D-016). Search analyses are
 * for the mall's administrators; a shop owner sees only that shop's views and funnel. Orders and sales
 * for the funnel come from the recorded order and sale records, only for real traffic: demonstration
 * traffic is never matched with real orders.
 */
class ActivityReportService
{
    public const LIST_LIMIT = 20;

    /**
     * @return array{searches: int, zero_results: int, masked: int, top_terms: Collection<int, array{term: string, searches: int, zero_results: int}>, unmet: Collection<int, array{term: string, zero_results: int, searches: int}>, categories: Collection<int, array{key: string, searches: int}>, floors: Collection<int, array{key: string, searches: int}>}
     */
    public function searchDemand(User $administrator, Mall $mall, ReportPeriod $period, DatasetLabel $label, ?string $kind = null): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $base = fn (SummaryMetric $metric): Builder => $this->summaries($mall, $period, $label, $metric)->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind));

        $totals = $base(SummaryMetric::SearchTotal)->selectRaw('coalesce(sum(value), 0) as searches, coalesce(sum(secondary), 0) as zero_results')->first();

        $termTotals = $base(SummaryMetric::SearchTerm)->selectRaw('subject_key, sum(value) as total')->groupBy('subject_key')->pluck('total', 'subject_key');
        $zeroTotals = $base(SummaryMetric::SearchTermZeroResult)->selectRaw('subject_key, sum(value) as total')->groupBy('subject_key')->pluck('total', 'subject_key');
        $withTerm = (int) $termTotals->sum();

        return [
            'searches' => (int) $totals->searches,
            'zero_results' => (int) $totals->zero_results,
            'masked' => (int) $totals->searches - $withTerm,
            'top_terms' => $termTotals->map(fn ($count, $term): array => ['term' => (string) $term, 'searches' => (int) $count, 'zero_results' => (int) ($zeroTotals[$term] ?? 0)])
                ->sortBy([['searches', 'desc'], ['term', 'asc']])->take(self::LIST_LIMIT)->values(),
            'unmet' => $zeroTotals->map(fn ($count, $term): array => ['term' => (string) $term, 'zero_results' => (int) $count, 'searches' => (int) ($termTotals[$term] ?? 0)])
                ->sortBy([['zero_results', 'desc'], ['term', 'asc']])->take(self::LIST_LIMIT)->values(),
            'categories' => $this->bySubject($base(SummaryMetric::SearchCategory)),
            'floors' => $this->bySubject($base(SummaryMetric::SearchFloor)),
        ];
    }

    /**
     * Views across the whole mall: per shop, per product and per day.
     *
     * @return array{shop_views: int, shop_visitors: int, shops: Collection<int, array{name: string, views: int, visitors: int}>, products: Collection<int, array{name: string, views: int, visitors: int}>, daily: Collection<string, int>}
     */
    public function mallViews(User $administrator, Mall $mall, ReportPeriod $period, DatasetLabel $label): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $shopRows = $this->summaries($mall, $period, $label, SummaryMetric::ShopPageViews)
            ->selectRaw('subject_key, sum(value) as views, sum(secondary) as visitors')->groupBy('subject_key')->get();
        $productRows = $this->summaries($mall, $period, $label, SummaryMetric::ProductPageViews)
            ->selectRaw('subject_key, sum(value) as views, sum(secondary) as visitors')->groupBy('subject_key')->get();

        $shopNames = Shop::query()->whereIn('shop_id', $shopRows->pluck('subject_key'))->pluck('name', 'shop_id');
        $productNames = Product::query()->whereIn('product_id', $productRows->pluck('subject_key'))->pluck('name', 'product_id');

        return [
            'shop_views' => (int) $shopRows->sum('views'),
            'shop_visitors' => (int) $shopRows->sum('visitors'),
            'shops' => $shopRows->map(fn ($row): array => ['name' => (string) ($shopNames[$row->subject_key] ?? __('Removed shop')), 'views' => (int) $row->views, 'visitors' => (int) $row->visitors])
                ->sortBy([['views', 'desc'], ['name', 'asc']])->values(),
            'products' => $productRows->map(fn ($row): array => ['name' => (string) ($productNames[$row->subject_key] ?? __('Removed product')), 'views' => (int) $row->views, 'visitors' => (int) $row->visitors])
                ->sortBy([['views', 'desc'], ['name', 'asc']])->take(self::LIST_LIMIT)->values(),
            'daily' => $this->daily($this->summaries($mall, $period, $label, SummaryMetric::ShopPageViews), $period),
        ];
    }

    /**
     * One shop's page views and, for real traffic, the funnel per listing: views of the product,
     * orders that contain the listing and recorded sales that contain it.
     *
     * @return array{shop_views: int, shop_visitors: int, daily: Collection<string, int>, funnel: Collection<int, array{label: string, product_views: int, orders: int, sales: int, conversion: ?float}>, funnel_available: bool}
     */
    public function shopAnalytics(User $owner, Shop $shop, ReportPeriod $period, DatasetLabel $label): array
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);

        $mall = $shop->loadMissing('mall')->mall;
        $views = $this->summaries($mall, $period, $label, SummaryMetric::ShopPageViews)->where('subject_key', $shop->getKey());
        $total = (clone $views)->selectRaw('coalesce(sum(value), 0) as views, coalesce(sum(secondary), 0) as visitors')->first();

        return [
            'shop_views' => (int) $total->views,
            'shop_visitors' => (int) $total->visitors,
            'daily' => $this->daily($views, $period),
            'funnel' => $this->funnel($shop, $mall, $period, $label),
            'funnel_available' => $label === DatasetLabel::Real,
        ];
    }

    /**
     * @return Collection<int, array{label: string, product_views: int, orders: int, sales: int, conversion: ?float}>
     */
    private function funnel(Shop $shop, Mall $mall, ReportPeriod $period, DatasetLabel $label): Collection
    {
        $listings = ShopProduct::query()->where('shop_id', $shop->getKey())->where('status', 'active')->with('variant.product')->get();

        $productViews = $this->summaries($mall, $period, $label, SummaryMetric::ProductPageViews)
            ->whereIn('subject_key', $listings->pluck('variant.product_id')->filter()->unique())
            ->selectRaw('subject_key, sum(value) as views')->groupBy('subject_key')->pluck('views', 'subject_key');

        $orders = $sales = collect();

        if ($label === DatasetLabel::Real) {
            $orders = DB::table('shop_order_items as items')->join('shop_orders as orders', 'orders.shop_order_id', '=', 'items.shop_order_id')
                ->where('orders.shop_id', $shop->getKey())->where('orders.created_at', '>=', $period->startsAt())->where('orders.created_at', '<', $period->endsAt())
                ->selectRaw('items.shop_product_id as listing, count(distinct orders.shop_order_id) as total')->groupBy('items.shop_product_id')->pluck('total', 'listing');
            $sales = DB::table('purchase_items as items')->join('purchases', 'purchases.purchase_id', '=', 'items.purchase_id')
                ->where('purchases.shop_id', $shop->getKey())->where('purchases.status', 'completed')->where('purchases.purchased_at', '>=', $period->startsAt())->where('purchases.purchased_at', '<', $period->endsAt())
                ->selectRaw('items.shop_product_id as listing, count(distinct purchases.purchase_id) as total')->groupBy('items.shop_product_id')->pluck('total', 'listing');
        }

        return $listings->map(function (ShopProduct $listing) use ($productViews, $orders, $sales, $label): array {
            $views = (int) ($productViews[$listing->variant?->product_id] ?? 0);
            $orderCount = (int) ($orders[$listing->getKey()] ?? 0);

            return [
                'label' => trim(($listing->variant?->product?->name ?? __('Removed product')).' · '.($listing->variant?->label ?? '')),
                'product_views' => $views,
                'orders' => $orderCount,
                'sales' => (int) ($sales[$listing->getKey()] ?? 0),
                'conversion' => $label === DatasetLabel::Real && $views > 0 ? round($orderCount / $views * 100, 1) : null,
            ];
        })->sortBy([['product_views', 'desc'], ['label', 'asc']])->values();
    }

    private function summaries(Mall $mall, ReportPeriod $period, DatasetLabel $label, SummaryMetric $metric): Builder
    {
        return DB::table('activity_daily_summaries')
            ->where('mall_id', $mall->getKey())->where('metric_code', $metric->value)->where('dataset_label', $label->value)
            ->where('day', '>=', $period->from->format('Y-m-d'))->where('day', '<=', $period->to->format('Y-m-d'));
    }

    /**
     * @return Collection<int, array{key: string, searches: int}>
     */
    private function bySubject(Builder $query): Collection
    {
        return $query->selectRaw('subject_key, sum(value) as total')->groupBy('subject_key')->get()
            ->map(fn ($row): array => ['key' => (string) $row->subject_key, 'searches' => (int) $row->total])
            ->sortBy([['searches', 'desc'], ['key', 'asc']])->values();
    }

    /**
     * Views per day for every day of the period, zero where nothing was recorded.
     *
     * @return Collection<string, int>
     */
    private function daily(Builder $query, ReportPeriod $period): Collection
    {
        $recorded = $query->selectRaw('day, sum(value) as total')->groupBy('day')->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day): array => [substr((string) $day, 0, 10) => (int) $total]);

        return collect(range(0, $period->dayCount() - 1))
            ->mapWithKeys(fn (int $offset): array => [$period->from->addDays($offset)->format('Y-m-d') => (int) ($recorded[$period->from->addDays($offset)->format('Y-m-d')] ?? 0)]);
    }
}
