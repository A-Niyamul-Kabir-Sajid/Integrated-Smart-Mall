<?php

namespace App\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use Database\Factories\FacilityFactory;
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
 * FACILITY: a public destination on one floor (decision D-013 for the extra
 * types and the location note).
 */
#[Table(name: 'facilities', key: 'facility_id', timestamps: false)]
#[Fillable(['floor_id', 'name', 'facility_type', 'is_accessible', 'status', 'location_note'])]
#[UseFactory(FacilityFactory::class)]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    /**
     * Facilities customers may see: open or temporarily closed.
     */
    #[Scope]
    protected function listed(Builder $query): Builder
    {
        return $query->whereIn('status', [FacilityStatus::Open->value, FacilityStatus::TemporarilyClosed->value]);
    }

    protected function casts(): array
    {
        return [
            'facility_type' => FacilityType::class,
            'status' => FacilityStatus::class,
            'is_accessible' => 'boolean',
        ];
    }
}
