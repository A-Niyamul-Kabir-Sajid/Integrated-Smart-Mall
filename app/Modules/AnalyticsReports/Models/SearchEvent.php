<?php

namespace App\Modules\AnalyticsReports\Models;

use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One recorded first-page search. Holds no person: only the normalised text (or none when it looked
 * personal), the filters used, how many results it returned and whether the visitor was signed in.
 */
#[Table(name: 'activity_search_events', key: 'activity_search_event_id', timestamps: false)]
#[Fillable(['mall_id', 'kind', 'term', 'term_masked', 'category', 'floor', 'result_count', 'signed_in', 'dataset_label', 'occurred_at'])]
class SearchEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Recorded searches cannot be changed.'));
    }

    protected function casts(): array
    {
        return [
            'term_masked' => 'boolean',
            'signed_in' => 'boolean',
            'result_count' => 'integer',
            'dataset_label' => DatasetLabel::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
