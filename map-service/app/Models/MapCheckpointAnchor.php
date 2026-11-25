<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a QR checkpoint hangs in one map (ERD CHECKPOINT_ANCHOR plus the
 * fiducial AR_REFERENCE_ANCHOR pose). The node is where the shopper stands,
 * 0.8 m in front of the code.
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $checkpoint_code
 * @property string|null $label
 * @property int $map_node_id
 * @property array{x_m: float, y_m: float, z_m: float, yaw_deg: float, marker_height_m?: float, physical_width_m?: float} $qr
 */
class MapCheckpointAnchor extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'checkpoint_code', 'label', 'map_node_id', 'qr'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['qr' => 'array'];
    }

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}
