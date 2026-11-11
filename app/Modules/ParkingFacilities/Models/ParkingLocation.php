<?php

namespace App\Modules\ParkingFacilities\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where an account parked in one mall (decision D-013). The key is
 * (user_id, mall_id); rows are written by ParkingService only.
 */
#[Table(name: 'parking_locations', key: 'mall_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['user_id', 'mall_id', 'parking_zone_id', 'bay_label', 'note', 'saved_at'])]
class ParkingLocation extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ParkingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'parking_zone_id');
    }

    protected function casts(): array
    {
        return [
            'saved_at' => 'immutable_datetime',
        ];
    }
}
