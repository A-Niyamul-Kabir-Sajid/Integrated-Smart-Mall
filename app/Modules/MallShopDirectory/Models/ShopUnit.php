<?php

namespace App\Modules\MallShopDirectory\Models;

use Database\Factories\ShopUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'shop_units', key: 'shop_unit_id', timestamps: false)]
#[Fillable(['shop_id', 'floor_id', 'unit_code', 'area_sq_m', 'status'])]
#[UseFactory(ShopUnitFactory::class)]
class ShopUnit extends Model
{
    /** @use HasFactory<ShopUnitFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    protected function casts(): array
    {
        return [
            'area_sq_m' => 'decimal:2',
        ];
    }
}
