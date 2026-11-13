<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum MaintenanceCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Cleaning = 'cleaning';
    case AirConditioning = 'air_conditioning';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plumbing => __('Plumbing'),
            self::Electrical => __('Electrical'),
            self::Cleaning => __('Cleaning'),
            self::AirConditioning => __('Air conditioning'),
            self::Security => __('Security'),
            self::Other => __('Other'),
        };
    }
}
