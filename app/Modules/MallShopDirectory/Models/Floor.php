<?php

namespace App\Modules\MallShopDirectory\Models;

use Database\Factories\FloorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FLOOR: a physical level of a mall, independent of any map version.
 */
#[Table(name: 'floors', key: 'floor_id', timestamps: false)]
#[Fillable(['mall_id', 'code', 'name', 'sort_order', 'elevation_m', 'status'])]
#[UseFactory(FloorFactory::class)]
class Floor extends Model
{
    /** @use HasFactory<FloorFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return HasMany<ShopUnit, $this>
     */
    public function shopUnits(): HasMany
    {
        return $this->hasMany(ShopUnit::class, 'floor_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'elevation_m' => 'decimal:3',
        ];
    }
}
