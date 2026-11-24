<?php

namespace App\Models;

use Database\Factories\AnnotationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop sign or advertisement placed in 3D on a map. Position and facing are
 * in the map's mall frame (metres, z up, yaw counterclockwise from +x).
 *
 * @property string $id
 * @property string $mall_map_id
 * @property string $code
 * @property string $kind
 * @property string|null $floor_code
 * @property string|null $unit_code
 * @property string $text
 * @property string $subtitle
 * @property string $background
 * @property string $color
 * @property string|null $image_path
 * @property float $width_m
 * @property float $lift_m
 * @property string $mount
 * @property float $x_m
 * @property float $y_m
 * @property float $z_m
 * @property float $yaw_deg
 */
class Annotation extends Model
{
    /** @use HasFactory<AnnotationFactory> */
    use HasFactory, HasUuids;

    public const KINDS = ['shop_sign', 'advertisement'];

    public const MOUNTS = ['wall', 'floor'];

    protected $fillable = [
        'code', 'kind', 'floor_code', 'unit_code', 'text', 'subtitle', 'background', 'color', 'image_path',
        'width_m', 'lift_m', 'mount', 'x_m', 'y_m', 'z_m', 'yaw_deg',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width_m' => 'float',
            'lift_m' => 'float',
            'x_m' => 'float',
            'y_m' => 'float',
            'z_m' => 'float',
            'yaw_deg' => 'float',
        ];
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}
