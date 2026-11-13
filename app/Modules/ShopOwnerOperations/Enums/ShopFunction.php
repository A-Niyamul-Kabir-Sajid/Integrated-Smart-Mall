<?php

namespace App\Modules\ShopOwnerOperations\Enums;

/**
 * The work function of a staff member (decision D-014). Owners are not listed
 * here: they may use every area. Staff without a recorded function may use only
 * the catalogue and the review inbox.
 */
enum ShopFunction: string
{
    case Manager = 'manager';
    case CatalogueEditor = 'catalogue_editor';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Manager => __('Manager'),
            self::CatalogueEditor => __('Catalogue editor'),
            self::Cashier => __('Cashier'),
        };
    }

    public function permits(ShopArea $area): bool
    {
        return match ($this) {
            self::Manager => true,
            self::CatalogueEditor => $area === ShopArea::Stock,
            self::Cashier => in_array($area, [ShopArea::Orders, ShopArea::Support], true),
        };
    }
}
