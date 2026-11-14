<?php

namespace App\Modules\AnalyticsReports\Models;

use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One metric value for one mall day (mall time zone), computed from the raw events together with
 * the formula version and the moment the data was read. Dashboards read these rows only.
 */
#[Table(name: 'activity_daily_summaries', key: 'activity_daily_summary_id', timestamps: false)]
#[Fillable(['mall_id', 'day', 'metric_code', 'kind', 'subject_key', 'shop_id', 'value', 'secondary', 'dataset_label', 'formula_version', 'source_cutoff_at'])]
class ActivitySummary extends Model
{
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'value' => 'integer',
            'secondary' => 'integer',
            'formula_version' => 'integer',
            'dataset_label' => DatasetLabel::class,
            'source_cutoff_at' => 'immutable_datetime',
        ];
    }
}
