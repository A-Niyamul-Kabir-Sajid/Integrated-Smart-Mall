<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point of the navigation graph (ERD NAVIGATION_NODE), in the map frame:
 * metres, x and y horizontal, z up.
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $floor_code
 * @property string $node_code
 * @property string $node_type junction, checkpoint, entrance, connector or facility
 * @property float $x_m
 * @property float $y_m
 * @property float $z_m
 * @property bool $is_accessible
 */
class MapNode extends Model
{
    public const TYPES = ['junction', 'checkpoint', 'entrance', 'connector', 'facility'];

    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'floor_code', 'node_code', 'node_type', 'x_m', 'y_m', 'z_m', 'is_accessible'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['x_m' => 'float', 'y_m' => 'float', 'z_m' => 'float', 'is_accessible' => 'boolean'];
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}
