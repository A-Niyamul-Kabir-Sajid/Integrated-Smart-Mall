<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lift, escalator or staircase joining floors (ERD VERTICAL_CONNECTOR).
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $name
 * @property string $connector_type elevator, escalator or stairs
 * @property string $direction both, up or down
 * @property bool $is_accessible
 */
class MapConnector extends Model
{
    public const TYPES = ['elevator', 'escalator', 'stairs'];

    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'name', 'connector_type', 'direction', 'is_accessible'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_accessible' => 'boolean'];
    }

    /**
     * @return HasMany<MapConnectorStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(MapConnectorStop::class);
    }
}
