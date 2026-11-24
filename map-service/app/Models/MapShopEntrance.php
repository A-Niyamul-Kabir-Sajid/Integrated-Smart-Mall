<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A doorway of a shop unit on the graph (ERD SHOP_ENTRANCE). The unit code is
 * the Smart Mall app's shop unit code, the only link between the two apps.
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $unit_code
 * @property int $map_node_id
 * @property bool $is_primary
 */
class MapShopEntrance extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'unit_code', 'map_node_id', 'is_primary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }
}
