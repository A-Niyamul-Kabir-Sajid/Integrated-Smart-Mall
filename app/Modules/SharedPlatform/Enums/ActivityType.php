<?php

namespace App\Modules\SharedPlatform\Enums;

/**
 * What a visitor did that the platform may count (decision D-016): a search or a page view.
 */
enum ActivityType: string
{
    case ShopSearch = 'shop_search';
    case ProductSearch = 'product_search';
    case ShopView = 'shop_view';
    case ProductView = 'product_view';

    public function isSearch(): bool
    {
        return $this === self::ShopSearch || $this === self::ProductSearch;
    }
}
