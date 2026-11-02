<?php

namespace App\Modules\MallShopDirectory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekly opening interval. The table has a composite primary key
 * (shop_id, weekday, interval_no), so rows are read through the shop
 * relationship and written with query-builder style inserts.
 */
#[Table(name: 'shop_opening_hours', incrementing: false, timestamps: false)]
#[Fillable(['shop_id', 'weekday', 'interval_no', 'opens_at', 'closes_at', 'closes_next_day'])]
class ShopOpeningHour extends Model
{
    protected $primaryKey = null;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'interval_no' => 'integer',
            'closes_next_day' => 'boolean',
        ];
    }
}
