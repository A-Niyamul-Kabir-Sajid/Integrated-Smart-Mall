<?php

namespace App\Models;

use Database\Factories\MallMapFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An imported map package: map_package.json plus its floor files, stored under
 * storage/app/private/{storage_dir}.
 *
 * @property string $id
 * @property string $name
 * @property string|null $mall_ref the Smart Mall app's mall slug this map belongs to
 * @property string $status
 * @property int $format_version
 * @property Carbon|null $package_generated_at
 * @property string $storage_dir
 * @property array<string, int> $stats
 * @property list<string> $floor_codes
 */
class MallMap extends Model
{
    /** @use HasFactory<MallMapFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'mall_ref', 'status', 'format_version', 'package_generated_at', 'storage_dir', 'stats', 'floor_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format_version' => 'integer',
            'package_generated_at' => 'datetime',
            'stats' => 'array',
            'floor_codes' => 'array',
        ];
    }

    /**
     * @return HasMany<Annotation, $this>
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class)->orderBy('code');
    }

    /**
     * @return HasMany<MapFloor, $this>
     */
    public function floors(): HasMany
    {
        return $this->hasMany(MapFloor::class)->orderBy('elevation_m');
    }

    /**
     * @return HasMany<MapNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(MapNode::class);
    }

    /**
     * @return HasMany<MapEdge, $this>
     */
    public function edges(): HasMany
    {
        return $this->hasMany(MapEdge::class);
    }

    /**
     * @return HasMany<MapConnector, $this>
     */
    public function connectors(): HasMany
    {
        return $this->hasMany(MapConnector::class);
    }

    /**
     * @return HasMany<MapShopEntrance, $this>
     */
    public function shopEntrances(): HasMany
    {
        return $this->hasMany(MapShopEntrance::class);
    }

    /**
     * @return HasMany<MapFacilityAnchor, $this>
     */
    public function facilityAnchors(): HasMany
    {
        return $this->hasMany(MapFacilityAnchor::class);
    }

    /**
     * @return HasMany<MapCheckpointAnchor, $this>
     */
    public function checkpointAnchors(): HasMany
    {
        return $this->hasMany(MapCheckpointAnchor::class);
    }

    /**
     * @return HasMany<MapEndPoint, $this>
     */
    public function endPoints(): HasMany
    {
        return $this->hasMany(MapEndPoint::class)->orderBy('name');
    }

    /**
     * @return HasMany<MapPath, $this>
     */
    public function paths(): HasMany
    {
        return $this->hasMany(MapPath::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<MapGraphEdit, $this>
     */
    public function graphEdits(): HasMany
    {
        return $this->hasMany(MapGraphEdit::class)->orderBy('sequence');
    }

    /**
     * The published map of a Smart Mall app mall, if any.
     */
    public static function publishedFor(string $mallRef): ?self
    {
        return self::query()->where('mall_ref', $mallRef)->where('status', 'published')->first();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * The decoded map_package.json.
     *
     * @return array<string, mixed>
     */
    public function package(): array
    {
        return json_decode(Storage::disk('local')->get($this->storage_dir.'/map_package.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
