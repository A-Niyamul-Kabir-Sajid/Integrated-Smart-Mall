<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a facility (toilet, prayer room, ATM, exit, ...) sits on the graph
 * (ERD FACILITY_ANCHOR).
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $facility_name
 * @property string $facility_type
 * @property int $map_node_id
 */
class MapFacilityAnchor extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'facility_name', 'facility_type', 'map_node_id'];

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }
}
