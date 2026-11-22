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

    protected $fillable = ['name', 'status', 'format_version', 'package_generated_at', 'storage_dir', 'stats', 'floor_codes'];

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
