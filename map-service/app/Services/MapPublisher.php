<?php

namespace App\Services;

use App\Models\MallMap;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Publishes and unpublishes maps. A mall (mall_ref) has at most one published
 * map: publishing a new one unpublishes the previous one in the same
 * transaction, so the API never shows two or none in between.
 */
class MapPublisher
{
    public const MALL_REF_PATTERN = '/^[a-z0-9][a-z0-9-]{0,99}$/';

    public const MALL_REF_MESSAGE = 'The mall must be the slug of a Smart Mall app mall: lower-case letters, digits and dashes.';

    public function publish(MallMap $map): void
    {
        DB::transaction(function () use ($map): void {
            if ($map->mall_ref !== null) {
                MallMap::query()
                    ->where('mall_ref', $map->mall_ref)
                    ->where('status', 'published')
                    ->whereKeyNot($map->getKey())
                    ->lockForUpdate()
                    ->update(['status' => 'draft', 'updated_at' => now()]);
            }
            $map->forceFill(['status' => 'published'])->save();
        });
    }

    public function unpublish(MallMap $map): void
    {
        $map->forceFill(['status' => 'draft'])->save();
    }

    /**
     * Link a map to a Smart Mall app mall (its slug), or unlink it with null.
     * A published map moves its publication with it.
     */
    public function assignMall(MallMap $map, ?string $mallRef): void
    {
        if ($mallRef !== null && ! preg_match(self::MALL_REF_PATTERN, $mallRef)) {
            throw new InvalidArgumentException(self::MALL_REF_MESSAGE);
        }

        DB::transaction(function () use ($map, $mallRef): void {
            $wasPublished = $map->isPublished();
            $map->forceFill(['mall_ref' => $mallRef, 'status' => 'draft'])->save();
            if ($wasPublished) {
                $this->publish($map);
            }
        });
    }
}
