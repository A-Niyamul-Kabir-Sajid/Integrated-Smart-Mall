<?php

namespace App\Models;

use Database\Factories\MapEndPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point an administrator added to a map (ISM-114): a plain node, a shop
 * entrance, a facility or a QR code. Apps route to it as "point:<key>"; a
 * shop entrance, facility or QR code also works like one from the package
 * (unit:, facility:, facility-type:, checkpoint:). Its node is null while
 * the edit cannot be applied ($problem says why).
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
 * @property float|null $link_m distance from the point to the walkway it is joined to; null when not joined
 * @property string $kind node, shop_entrance, facility or checkpoint
 * @property int $sequence
 * @property string|null $unit_code
 * @property bool $is_primary
 * @property string|null $facility_type
 * @property string|null $checkpoint_code
 * @property array{x_m: float, y_m: float, z_m: float, yaw_deg: float, marker_height_m: float, physical_width_m: float}|null $qr
 * @property string|null $problem
 * @property float|null $height_m height of the marker (or the QR code) above the floor
 */
class MapEndPoint extends Model
{
    /** @use HasFactory<MapEndPointFactory> */
    use HasFactory;

    public const KINDS = [
        'node' => 'Node',
        'shop_entrance' => 'Shop entrance',
        'facility' => 'Facility',
        'checkpoint' => 'QR code',
    ];

    /** Height above the floor a new point's marker gets, and a new QR code. */
    public const DEFAULT_HEIGHT_M = 1.5;

    public const DEFAULT_QR_HEIGHT_M = 1.4;

    /** The node type each kind gets in the graph. */
    public const NODE_TYPES = ['node' => 'endpoint', 'shop_entrance' => 'entrance', 'facility' => 'facility', 'checkpoint' => 'checkpoint'];

    protected $fillable = ['mall_map_id', 'key', 'kind', 'sequence', 'name', 'floor_code', 'x_m', 'y_m', 'z_m', 'map_node_id', 'link_m', 'unit_code', 'is_primary', 'facility_type', 'checkpoint_code', 'qr', 'problem', 'height_m'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x_m' => 'float',
            'y_m' => 'float',
            'z_m' => 'float',
            'link_m' => 'float',
            'map_node_id' => 'integer',
            'sequence' => 'integer',
            'is_primary' => 'boolean',
            'qr' => 'array',
            'height_m' => 'float',
        ];
    }

    /**
     * The point is in the graph (it may still lack a walkway to it: see link_m).
     */
    public function isLinked(): bool
    {
        return $this->map_node_id !== null;
    }

    /**
     * Where its graph node goes: the point itself, or for a QR code the spot
     * $standoff metres in front of the code where the visitor stands.
     *
     * @return array{0: float, 1: float}
     */
    public function nodePosition(float $standoff): array
    {
        if ($this->kind !== 'checkpoint' || $this->qr === null) {
            return [$this->x_m, $this->y_m];
        }
        $yaw = deg2rad((float) $this->qr['yaw_deg']);

        return [round($this->x_m + $standoff * cos($yaw), 3), round($this->y_m + $standoff * sin($yaw), 3)];
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
