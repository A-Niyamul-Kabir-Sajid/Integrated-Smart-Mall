<?php

namespace App\Modules\AnalyticsReports\Models;

use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One recorded page view. The visitor key is a keyed hash that changes every day, so a visitor can
 * be counted once per day but never followed across days, and it cannot be reversed to a person.
 */
#[Table(name: 'activity_view_events', key: 'activity_view_event_id', timestamps: false)]
#[Fillable(['mall_id', 'kind', 'shop_id', 'product_id', 'visitor_key', 'dataset_label', 'occurred_at'])]
class ViewEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Recorded views cannot be changed.'));
    }

    protected function casts(): array
    {
        return [
            'dataset_label' => DatasetLabel::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
