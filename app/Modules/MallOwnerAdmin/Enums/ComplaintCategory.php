<?php

namespace App\Modules\MallOwnerAdmin\Enums;

enum ComplaintCategory: string
{
    case Cleanliness = 'cleanliness';
    case Safety = 'safety';
    case StaffConduct = 'staff_conduct';
    case Facilities = 'facilities';
    case ShopService = 'shop_service';
    case Noise = 'noise';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cleanliness => __('Cleanliness'),
            self::Safety => __('Safety'),
            self::StaffConduct => __('Staff conduct'),
            self::Facilities => __('Facilities'),
            self::ShopService => __('Shop service'),
            self::Noise => __('Noise'),
            self::Other => __('Other'),
        };
    }
}
