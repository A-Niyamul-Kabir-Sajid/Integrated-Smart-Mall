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

    public function __construct(private CheckpointRegistry $checkpoints) {}

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
            $this->writeStatus($map, 'published');
            $this->checkpoints->syncFromMap($map);
        });
    }

    public function unpublish(MallMap $map): void
    {
        $this->writeStatus($map, 'draft');
    }

    /**
     * Write the status even when the model in memory already shows it: another
     * publish may have changed the row since the model was loaded.
     */
    private function writeStatus(MallMap $map, string $status): void
    {
        $map->newQuery()->whereKey($map->getKey())->update(['status' => $status, 'updated_at' => now()]);
        $map->forceFill(['status' => $status])->syncOriginalAttribute('status');
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
