<?php

namespace App\Modules\AnalyticsReports\Enums;

/**
 * The metric catalogue of the shop and mall dashboards, monthly reports and exports (Jira EPIC-12,
 * decision D-017). Every figure shown or exported has a code listed here with its business meaning,
 * source tables, formula, dimensions, refresh strategy and who may see it. When a definition changes,
 * add a new code and keep the old one; never edit a published formula. Visitor-activity metrics have their
 * own catalogue in SummaryMetric and are listed beside these by MetricCatalogue.
 */
enum ReportMetric: string
{
    // Shop and mall: sales (recorded transactions only)
    case SalesRevenue = 'sales_revenue';
    case SalesCount = 'sales_count';
    case AverageSaleValue = 'average_sale_value';
    case UnitsSold = 'units_sold';

    // Orders and reservations
    case OrdersPlaced = 'orders_placed';
    case OrdersCompleted = 'orders_completed';
    case ReservationsPlaced = 'reservations_placed';
    case OrderCompletionRate = 'order_completion_rate';

    // Returns
    case ReturnsCompleted = 'returns_completed';
    case ReturnsValue = 'returns_value';

    // Inventory (a snapshot at the moment the report is read)
    case StockUnitsOnHand = 'stock_units_on_hand';
    case StockOutListings = 'stock_out_listings';
    case LowStockListings = 'low_stock_listings';

    // Feedback
    case ReviewsPublished = 'reviews_published';
    case RatingAverage = 'rating_average';
    case RatingTrend = 'rating_trend';
    case SupportComplaints = 'support_complaints';

    // Campaigns
    case CouponRedemptions = 'coupon_redemptions';
    case DiscountGiven = 'discount_given';

    // Mall only
    case ActiveShops = 'active_shops';
    case ShopsWithSales = 'shops_with_sales';
    case RatingAdjusted = 'rating_adjusted';
    case ComplaintsFiled = 'complaints_filed';
    case ComplaintsResolved = 'complaints_resolved';
    case ComplaintsUnresolved = 'complaints_unresolved';
    case ComplaintResolutionHours = 'complaint_resolution_hours';
    case NavigationRequests = 'navigation_requests';

    // Analyses built on the figures above
    case ProductClasses = 'product_classes';
    case SalesAnomalies = 'sales_anomalies';
    case ReviewTerms = 'review_terms';
    case ShopSegments = 'shop_segments';
    case QualityFlags = 'quality_flags';
    case CategoryDemand = 'category_demand';
    case FloorDemand = 'floor_demand';
    case SalesForecast = 'sales_forecast';

    public const FORMULA_VERSION = 1;

    public const SHOP = 'shop';

    public const MALL = 'mall';

    public function label(): string
    {
        return $this->definition()['label'];
    }

    /**
     * "shop" (the owner of that shop), "mall" (administrators of the mall) or "both".
     */
    public function scope(): string
    {
        return $this->definition()['scope'];
    }

    public function unit(): string
    {
        return $this->definition()['unit'];
    }

    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return $this->definition()['sources'];
    }

    public function formula(): string
    {
        return $this->definition()['formula'];
    }

    public function dimensions(): string
    {
        return $this->definition()['dimensions'];
    }

    public function refresh(): string
    {
        return $this->definition()['refresh'] ?? 'Computed from the source records every time the report is opened or exported.';
    }

    /**
     * The method behind an analysis, or null for a plain count or sum.
     */
    public function algorithm(): ?string
    {
        return $this->definition()['algorithm'] ?? null;
    }

    /**
     * False for a metric defined here but with no data source yet; its reason is given by unavailableReason().
     */
    public function available(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        return $this->definition()['unavailable'] ?? null;
    }

    public function visibility(): string
    {
        return match ($this->scope()) {
            self::SHOP => 'The owner of the shop concerned (and platform operators); not mall administrators, not other shops',
            self::MALL => 'Administrators of the mall (and platform operators); not shop owners',
            default => 'The owner of the shop for that shop only; administrators of the mall for every shop of their mall',
        };
    }

    public function visibleToShop(): bool
    {
        return $this->scope() !== self::MALL;
    }

    public function visibleToMall(): bool
    {
        return $this->scope() !== self::SHOP;
    }

    /**
     * @return array{label: string, scope: string, unit: string, sources: list<string>, formula: string, dimensions: string, refresh?: string, algorithm?: string, unavailable?: string}
     */
    private function definition(): array
    {
        $sale = ['purchases', 'purchase_items'];
        $day = 'Mall day (mall time zone); shop';

        return match ($this) {
            self::SalesRevenue => ['label' => __('Recorded sales revenue'), 'scope' => 'both', 'unit' => 'BDT', 'sources' => ['purchases'], 'dimensions' => "$day, product", 'formula' => 'Sum of total_amount of purchases with status completed whose purchased_at falls in the period. Voided and refunded sales and online orders that were never recorded as a sale are not counted.'],
            self::SalesCount => ['label' => __('Recorded sales'), 'scope' => 'both', 'unit' => 'sales', 'sources' => ['purchases'], 'dimensions' => "$day", 'formula' => 'Count of purchases with status completed whose purchased_at falls in the period.'],
            self::AverageSaleValue => ['label' => __('Average sale value'), 'scope' => 'both', 'unit' => 'BDT', 'sources' => ['purchases'], 'dimensions' => "$day", 'formula' => 'recorded sales revenue divided by recorded sales; empty (not 0) when there were no sales.'],
            self::UnitsSold => ['label' => __('Units sold'), 'scope' => 'both', 'unit' => 'units', 'sources' => $sale, 'dimensions' => "$day, product", 'formula' => 'Sum of quantity over the lines of completed purchases in the period.'],
            self::OrdersPlaced => ['label' => __('Orders placed'), 'scope' => 'both', 'unit' => 'orders', 'sources' => ['shop_orders'], 'dimensions' => "$day, kind, status", 'formula' => 'Count of reservations and click-and-collect orders created in the period, in any status.'],
            self::OrdersCompleted => ['label' => __('Orders completed'), 'scope' => 'both', 'unit' => 'orders', 'sources' => ['shop_orders'], 'dimensions' => "$day", 'formula' => 'Count of orders created in the period that now have status completed (collected by the customer).'],
            self::ReservationsPlaced => ['label' => __('Reservations placed'), 'scope' => 'both', 'unit' => 'orders', 'sources' => ['shop_orders'], 'dimensions' => "$day", 'formula' => 'Count of orders of kind reservation created in the period.'],
            self::OrderCompletionRate => ['label' => __('Order completion rate'), 'scope' => 'both', 'unit' => '%', 'sources' => ['shop_orders'], 'dimensions' => "$day", 'formula' => 'orders completed divided by orders placed, times 100; empty when no order was placed. Orders still open count as not completed.'],
            self::ReturnsCompleted => ['label' => __('Returns and exchanges completed'), 'scope' => 'both', 'unit' => 'returns', 'sources' => ['shop_returns'], 'dimensions' => "$day", 'formula' => 'Count of returns and exchanges with status completed whose completed_at falls in the period.'],
            self::ReturnsValue => ['label' => __('Value of completed returns'), 'scope' => 'both', 'unit' => 'BDT', 'sources' => ['shop_returns'], 'dimensions' => "$day", 'formula' => 'Sum of value_amount of the returns counted in returns completed.'],
            self::StockUnitsOnHand => ['label' => __('Units in stock'), 'scope' => 'shop', 'unit' => 'units', 'sources' => ['listing_stocks', 'shop_products'], 'dimensions' => 'Listing', 'refresh' => 'A snapshot of stock at the moment the report is read; it does not depend on the period.', 'formula' => 'Sum of on_hand over the shop\'s active listings that track stock.'],
            self::StockOutListings => ['label' => __('Listings out of stock'), 'scope' => 'shop', 'unit' => 'listings', 'sources' => ['listing_stocks', 'shop_products'], 'dimensions' => 'Listing', 'refresh' => 'A snapshot at the moment the report is read.', 'formula' => 'Count of active listings that track stock with on_hand equal to 0.'],
            self::LowStockListings => ['label' => __('Listings with low stock'), 'scope' => 'shop', 'unit' => 'listings', 'sources' => ['listing_stocks', 'shop_products'], 'dimensions' => 'Listing', 'refresh' => 'A snapshot at the moment the report is read.', 'formula' => 'Count of active listings with on_hand above 0 and at most low_stock_threshold.'],
            self::ReviewsPublished => ['label' => __('Published reviews'), 'scope' => 'both', 'unit' => 'reviews', 'sources' => ['reviews'], 'dimensions' => "$day, rating", 'formula' => 'Count of reviews with status published created in the period.'],
            self::RatingAverage => ['label' => __('Average rating'), 'scope' => 'both', 'unit' => 'stars', 'sources' => ['reviews'], 'dimensions' => 'Shop, rating', 'formula' => 'Mean rating (1 to 5) of the published reviews created in the period; always shown with the number of reviews, empty when there are none.'],
            self::RatingTrend => ['label' => __('Rating trend'), 'scope' => 'both', 'unit' => 'stars per 30 days', 'sources' => ['reviews'], 'dimensions' => 'Shop', 'algorithm' => 'Ordinary least squares of rating on review date with a two-sided t-test of the slope (Student t, n − 2 degrees of freedom)', 'formula' => 'Slope of a least-squares line through published ratings of the 120 days ending with the period, times 30, with its p-value. Reported only with at least 8 reviews spread over at least 28 days; a trend is called significant at p below 0.10.'],
            self::SupportComplaints => ['label' => __('Complaints sent to the shop'), 'scope' => 'shop', 'unit' => 'threads', 'sources' => ['support_threads'], 'dimensions' => "$day, status", 'formula' => 'Count of support threads of kind complaint created in the period (customer messages to the shop; separate from complaints to mall management).'],
            self::CouponRedemptions => ['label' => __('Promotion redemptions'), 'scope' => 'both', 'unit' => 'redemptions', 'sources' => ['promotion_redemptions', 'shop_promotions'], 'dimensions' => 'Promotion', 'formula' => 'Count of redemptions made in the period that were not released again (the order was cancelled, rejected or expired).'],
            self::DiscountGiven => ['label' => __('Discount given through promotions'), 'scope' => 'both', 'unit' => 'BDT', 'sources' => ['promotion_redemptions'], 'dimensions' => 'Promotion', 'formula' => 'Sum of discount_amount of the redemptions counted in promotion redemptions.'],
            self::ActiveShops => ['label' => __('Active shops'), 'scope' => 'mall', 'unit' => 'shops', 'sources' => ['shops'], 'dimensions' => 'Category, floor', 'refresh' => 'The number of shops with status active at the moment the report is read.', 'formula' => 'Count of shops of the mall with status active.'],
            self::ShopsWithSales => ['label' => __('Shops with a recorded sale'), 'scope' => 'mall', 'unit' => 'shops', 'sources' => ['purchases'], 'dimensions' => 'Category, floor', 'formula' => 'Count of shops with at least one completed purchase in the period; shows how many shops the sales figures cover.'],
            self::RatingAdjusted => ['label' => __('Adjusted rating'), 'scope' => 'mall', 'unit' => 'stars', 'sources' => ['reviews'], 'dimensions' => 'Shop', 'algorithm' => 'Empirical-Bayes shrinkage of shop means towards the mall mean (method-of-moments prior weight)', 'formula' => '(κ · mall mean + sum of ratings) / (κ + number of reviews), where κ = within-shop variance / between-shop variance estimated from the data (limited to 0.5–50, 5 when it cannot be estimated). Makes shops with few reviews comparable with shops with many; always shown beside the raw mean and the review count.'],
            self::ComplaintsFiled => ['label' => __('Complaints filed'), 'scope' => 'mall', 'unit' => 'complaints', 'sources' => ['complaints'], 'dimensions' => 'Category, shop, status', 'formula' => 'Count of complaints to mall management created in the period.'],
            self::ComplaintsResolved => ['label' => __('Complaints resolved'), 'scope' => 'mall', 'unit' => 'complaints', 'sources' => ['complaints'], 'dimensions' => 'Category, shop', 'formula' => 'Count of the complaints filed in the period that now have status resolved or closed.'],
            self::ComplaintsUnresolved => ['label' => __('Complaints still open'), 'scope' => 'mall', 'unit' => 'complaints', 'sources' => ['complaints'], 'dimensions' => 'Category, shop', 'formula' => 'Count of the complaints filed in the period that now have status open, assigned or in progress.'],
            self::ComplaintResolutionHours => ['label' => __('Median time to resolve a complaint'), 'scope' => 'mall', 'unit' => 'hours', 'sources' => ['complaints'], 'dimensions' => 'Category', 'algorithm' => 'Median', 'formula' => 'Median of closed_at − created_at in hours over the complaints counted in complaints resolved; empty when none was resolved.'],
            self::NavigationRequests => ['label' => __('Navigation requests'), 'scope' => 'mall', 'unit' => 'requests', 'sources' => [], 'dimensions' => 'Destination, route', 'formula' => 'Count of route requests per destination. Measures interest in a destination, never confirmed visits or footfall.', 'unavailable' => 'Not available yet: the maps and navigation module (EPIC-05) is not built, so no route requests are recorded.'],
            self::ProductClasses => ['label' => __('Product classes (ABC and XYZ)'), 'scope' => 'shop', 'unit' => 'class', 'sources' => $sale, 'dimensions' => 'Listing', 'algorithm' => 'Pareto ABC analysis of revenue; XYZ by coefficient of variation of weekly units', 'formula' => 'ABC: listings ranked by revenue; A brings the first 80% of revenue, B the next 15%, C the rest and every listing without revenue. XYZ: standard deviation / mean of weekly units sold over the full weeks of the period; X below 0.5, Y below 1.0, Z otherwise or when nothing sold. Needs at least two full weeks.'],
            self::SalesAnomalies => ['label' => __('Unusual sales days'), 'scope' => 'both', 'unit' => 'days', 'sources' => ['purchases'], 'dimensions' => 'Day', 'algorithm' => 'Weekday-adjusted modified z-score (median and MAD, Iglewicz and Hoaglin)', 'formula' => 'Each day\'s revenue minus the median revenue of the same weekday in the period; modified z-score of these gaps 0.6745 · gap / MAD; days beyond ±3.5 are listed. Needs at least 28 days with 10 days of sales.'],
            self::ReviewTerms => ['label' => __('Words in praise and in complaints'), 'scope' => 'both', 'unit' => 'terms', 'sources' => ['reviews'], 'dimensions' => 'Word', 'algorithm' => 'Weighted log-odds ratio with an informative Dirichlet prior (Monroe, Colaresi and Quinn)', 'formula' => 'Words that appear in reviews rated 1–2 more (or less) than in reviews rated 4–5, as a z-score; words used less than twice and common English words are left out; needs 3 reviews on each side. English only.'],
            self::ShopSegments => ['label' => __('Shop performance segments'), 'scope' => 'mall', 'unit' => 'segments', 'sources' => ['purchases', 'reviews', 'activity_daily_summaries'], 'dimensions' => 'Shop', 'algorithm' => 'k-means clustering (k-means++ seeding, 10 restarts, fixed seed) with k chosen by mean silhouette', 'formula' => 'Shops with any activity are described by log(1 + revenue), log(1 + recorded sales), adjusted rating and log(1 + page views), standardised; k from 2 to 4 is chosen by silhouette. Segments are named by mean revenue. Needs at least 5 shops with activity; a description of the period, not a ranking of merit.'],
            self::QualityFlags => ['label' => __('Service-quality flags'), 'scope' => 'mall', 'unit' => 'flags', 'sources' => ['reviews', 'complaints', 'purchases'], 'dimensions' => 'Shop', 'algorithm' => 'Rating-slope t-test, complaint-count outliers by modified z-score, rule thresholds', 'formula' => 'A shop is flagged for investigation when (1) its rating trend is significantly negative (p below 0.10, slope below −0.25 stars per 30 days), (2) it has three or more complaints still open for over seven days, (3) its complaints in the period are an outlier (modified z-score 3.5 or more, at least 3 complaints), or (4) its revenue fell by 40% or more from the previous period of equal length (previous revenue at least BDT 1,000). Every flag lists its evidence; a flag is a prompt to look, not a finding.'],
            self::CategoryDemand => ['label' => __('Demand by shop category'), 'scope' => 'mall', 'unit' => 'mixed', 'sources' => ['purchases', 'shop_orders', 'activity_daily_summaries'], 'dimensions' => 'Shop category', 'formula' => 'Recorded sales revenue, orders and shop-page views of the shops in each category, and searches that used the category filter.'],
            self::FloorDemand => ['label' => __('Demand by floor'), 'scope' => 'mall', 'unit' => 'mixed', 'sources' => ['purchases', 'shop_orders', 'shop_units', 'floors'], 'dimensions' => 'Floor', 'formula' => 'Recorded sales revenue and orders of the shops whose unit is on each floor (the first non-archived unit when a shop has several), and searches that used the floor filter. Not footfall.'],
            self::SalesForecast => ['label' => __('Sales forecast (prototype)'), 'scope' => 'both', 'unit' => 'BDT per day', 'sources' => ['purchases'], 'dimensions' => 'Day', 'algorithm' => 'Ridge regression (trend + weekday), Holt–Winters damped additive smoothing and two seasonal baselines, chosen by rolling-origin cross-validation and judged on a held-out final 14 days', 'formula' => 'Target: recorded sales revenue per mall day. See the forecast page for the data, baselines, errors (MAE, RMSE, sMAPE, MASE) and limits. An estimate, not a promise.', 'refresh' => 'Recomputed from the sales history every time the page is opened; the same history always gives the same result (run id).'],
        };
    }
}
