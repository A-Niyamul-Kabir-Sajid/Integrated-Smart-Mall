<?php

namespace App\Modules\AnalyticsReports\Enums;

/**
 * The visitor-activity metric catalogue (Jira EPIC-14, decision D-016). Each code has one meaning
 * and one formula version: when a definition changes, add a new code instead of editing the old one.
 */
enum SummaryMetric: string
{
    case SearchTotal = 'search_total';
    case SearchTerm = 'search_term_count';
    case SearchTermZeroResult = 'search_term_zero_result_count';
    case SearchCategory = 'search_category_count';
    case SearchFloor = 'search_floor_count';
    case ShopPageViews = 'shop_page_views';
    case ProductPageViews = 'product_page_views';

    public const FORMULA_VERSION = 1;

    public function label(): string
    {
        return match ($this) {
            self::SearchTotal => __('Searches'),
            self::SearchTerm => __('Searches for a term'),
            self::SearchTermZeroResult => __('Searches for a term with no results'),
            self::SearchCategory => __('Searches filtered by a category'),
            self::SearchFloor => __('Searches filtered by a floor'),
            self::ShopPageViews => __('Shop page views'),
            self::ProductPageViews => __('Product page views'),
        };
    }

    /**
     * What is counted and how, in words a mall owner can check against the raw events.
     */
    public function formula(): string
    {
        return match ($this) {
            self::SearchTotal => 'Count of recorded first-page searches that day (value) and how many of them returned no result (secondary), including searches whose text was masked.',
            self::SearchTerm => 'Count of recorded first-page searches whose normalised text equals the term, per search kind (shop or product).',
            self::SearchTermZeroResult => 'The same count restricted to searches that returned no result.',
            self::SearchCategory => 'Count of recorded searches that used the category filter.',
            self::SearchFloor => 'Count of recorded searches that used the floor filter.',
            self::ShopPageViews => 'Count of recorded views of the shop page (value) and distinct anonymous visitors that day (secondary); one view per visitor per page per 30 minutes.',
            self::ProductPageViews => 'Count of recorded views of the product page (value) and distinct anonymous visitors that day (secondary); shared by every shop that sells the product.',
        };
    }

    /**
     * Who may read it: search metrics belong to the whole mall, view metrics to the mall and the shop.
     */
    public function visibility(): string
    {
        return match ($this) {
            self::ShopPageViews, self::ProductPageViews => 'Mall administrators; the owner of the shop concerned',
            default => 'Mall administrators only',
        };
    }

    public function sourceTable(): string
    {
        return match ($this) {
            self::ShopPageViews, self::ProductPageViews => 'activity_view_events',
            default => 'activity_search_events',
        };
    }
}
