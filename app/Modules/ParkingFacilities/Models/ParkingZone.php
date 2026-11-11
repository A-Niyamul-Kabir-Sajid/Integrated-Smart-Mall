<?php

namespace App\Modules\ParkingFacilities\Models;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use Database\Factories\ParkingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A marked area of a mall's car park on one floor (decision D-013).
 */
#[Table(name: 'parking_zones', key: 'parking_zone_id', timestamps: false)]
#[Fillable(['mall_id', 'floor_id', 'code', 'name', 'capacity', 'status'])]
#[UseFactory(ParkingZoneFactory::class)]
class ParkingZone extends Model
{
    /** @use HasFactory<ParkingZoneFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }
}
