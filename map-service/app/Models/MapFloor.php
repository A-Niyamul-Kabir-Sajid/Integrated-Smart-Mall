<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One floor of an imported map (ERD FLOOR_MAP).
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $floor_code
 * @property string $name
 * @property float $elevation_m
 */
class MapFloor extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'floor_code', 'name', 'elevation_m'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['elevation_m' => 'float'];
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}
