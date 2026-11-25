<?php

namespace App\Models;

use Database\Factories\MapPathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A walkway an administrator drew on one floor (ISM-114): corners in the map
 * frame, joined in order. A corner near an existing node or walkway joins
 * it; the others become new nodes.
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $key
 * @property int $sequence
 * @property string|null $name
 * @property string $floor_code
 * @property list<array{0: float, 1: float}> $points
 * @property bool $is_accessible
 * @property bool $one_way
 * @property string|null $problem
 */
class MapPath extends Model
{
    /** @use HasFactory<MapPathFactory> */
    use HasFactory;

    protected $fillable = ['mall_map_id', 'key', 'sequence', 'name', 'floor_code', 'points', 'is_accessible', 'one_way', 'problem'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['points' => 'array', 'is_accessible' => 'boolean', 'one_way' => 'boolean', 'sequence' => 'integer'];
    }

    /**
     * Length along the corners, in metres.
     */
    public function length(): float
    {
        $length = 0.0;
        $points = array_values($this->points);
        for ($i = 1; $i < count($points); $i++) {
            $length += hypot($points[$i][0] - $points[$i - 1][0], $points[$i][1] - $points[$i - 1][1]);
        }

        return round($length, 1);
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}
