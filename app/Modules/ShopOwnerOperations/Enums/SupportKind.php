<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum SupportKind: string
{
    case Question = 'question';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Question => __('Question'),
            self::Complaint => __('Complaint'),
        };
    }
}
