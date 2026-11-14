<?php

namespace App\Modules\AnalyticsReports\Enums;

/**
 * Where an activity record came from. Real traffic and seeded demonstration traffic are never
 * added together, and a screen always says which one it shows.
 */
enum DatasetLabel: string
{
    case Real = 'real';
    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Real => __('Real visitor traffic'),
            self::Demo => __('Demonstration data (seeded, not real visitors)'),
        };
    }
}
