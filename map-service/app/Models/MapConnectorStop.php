<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a connector stops on one floor (ERD CONNECTOR_STOP).
 *
 * @property int $id
 * @property string $mall_map_id
 * @property int $map_connector_id
 * @property int $map_node_id
 * @property string $floor_code
 */
class MapConnectorStop extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'map_connector_id', 'map_node_id', 'floor_code'];

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }
}
