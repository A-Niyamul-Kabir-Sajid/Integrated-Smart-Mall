<?php

namespace App\Modules\AccountsAccess\Enums;

/**
 * The role catalogue (decision D-008). Rows with these codes are created by the
 * roles migration.
 */
enum RoleCode: string
{
    case Customer = 'customer';
    case PlatformAdmin = 'platform_admin';
    case MallAdmin = 'mall_admin';
    case ShopOwner = 'shop_owner';
    case ShopStaff = 'shop_staff';

    public function scope(): RoleScope
    {
        return match ($this) {
            self::Customer, self::PlatformAdmin => RoleScope::Platform,
            self::MallAdmin => RoleScope::Mall,
            self::ShopOwner, self::ShopStaff => RoleScope::Shop,
        };
    }
}
