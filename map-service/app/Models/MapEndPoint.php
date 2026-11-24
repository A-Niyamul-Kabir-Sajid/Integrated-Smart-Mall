<?php

namespace App\Models;

use Database\Factories\MapEndPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A destination an administrator added to a map (ISM-114). Apps route to it
 * as "point:<key>". Its node is null while it cannot be joined to a walkway
 * (for example in a new map version without a walkway near it).
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $key
 * @property string $name
 * @property string $floor_code
 * @property float $x_m
 * @property float $y_m
 * @property float $z_m
 * @property int|null $map_node_id
 * @property float|null $link_m distance from the point to the walkway it is joined to
 */
class MapEndPoint extends Model
{
    /** @use HasFactory<MapEndPointFactory> */
    use HasFactory;

    protected $fillable = ['mall_map_id', 'key', 'name', 'floor_code', 'x_m', 'y_m', 'z_m', 'map_node_id', 'link_m'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['x_m' => 'float', 'y_m' => 'float', 'z_m' => 'float', 'link_m' => 'float', 'map_node_id' => 'integer'];
    }

    public function isLinked(): bool
    {
        return $this->map_node_id !== null;
    }

    public function place(): string
    {
        return 'point:'.$this->key;
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }

    /**
     * @return BelongsTo<MapNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }
}
