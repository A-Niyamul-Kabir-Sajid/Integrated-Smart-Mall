<?php

namespace App\Modules\AnalyticsReports\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored monthly report of one shop or one mall: a snapshot of the dashboard figures for the month,
 * the narrative drafted from them and the catalogue version, as of the moment the data was read. The month
 * is kept as the plain date string of its first day (for example 2026-10-01), not cast, so it compares the same on every database.
 */
#[Table(name: 'analytics_monthly_reports', key: 'report_id')]
#[Fillable(['scope', 'mall_id', 'shop_id', 'subject_key', 'month', 'metric_version', 'partial', 'source_cutoff_at', 'payload', 'generated_by_user_id'])]
class MonthlyReport extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'metric_version' => 'integer',
            'partial' => 'boolean',
            'source_cutoff_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
