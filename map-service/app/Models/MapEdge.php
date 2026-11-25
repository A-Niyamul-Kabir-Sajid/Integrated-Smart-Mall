<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A directed walkable link between two nodes of one map (ERD NAVIGATION_EDGE).
 * Geometry and costs come from the package and do not change; only the
 * status may be switched to "closed" as a temporary operational exception,
 * which makes the route engine avoid it.
 *
 * @property int $id
 * @property string $mall_map_id
 * @property int $from_node_id
 * @property int $to_node_id
 * @property float $length_m
 * @property float $expected_seconds
 * @property bool $is_accessible
 * @property string $status open or closed
 * @property int|null $map_connector_id
 * @property string|null $closed_reason
 * @property Carbon|null $status_changed_at
 */
class MapEdge extends Model
{
    public $timestamps = false;

    protected $fillable = ['mall_map_id', 'from_node_id', 'to_node_id', 'length_m', 'expected_seconds', 'is_accessible', 'status', 'map_connector_id', 'closed_reason', 'status_changed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'length_m' => 'float',
            'expected_seconds' => 'float',
            'is_accessible' => 'boolean',
            'status_changed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'from_node_id');
    }

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function toNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'to_node_id');
    }

    /**
     * @return BelongsTo<MapConnector, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(MapConnector::class, 'map_connector_id');
    }
}
