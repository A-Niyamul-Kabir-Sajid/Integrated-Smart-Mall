<?php

namespace App\Services;

use App\Exceptions\CheckpointUnavailable;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\MapCheckpointAnchor;
use Illuminate\Support\Str;

/**
 * The life of QR checkpoints (ISM-61): created with a random public slug when
 * a map of their mall is published, retired and reactivated by
 * administrators, given a new slug when a printed code must stop working,
 * and resolved from a scan to the anchor in the mall's published map.
 */
class CheckpointRegistry
{
    /**
     * Make sure every QR checkpoint placed in $map exists for its mall. Existing
     * checkpoints keep their slug (so printed codes keep working) and get the
     * label of the newest map.
     */
    public function syncFromMap(MallMap $map): void
    {
        if ($map->mall_ref === null) {
            return;
        }

        foreach ($map->checkpointAnchors()->get() as $anchor) {
            $checkpoint = Checkpoint::query()->firstOrNew(['mall_ref' => $map->mall_ref, 'checkpoint_code' => $anchor->checkpoint_code]);
            if (! $checkpoint->exists) {
                $checkpoint->public_slug = $this->newSlug();
                $checkpoint->status = 'active';
            }
            $checkpoint->label = $anchor->label;
            $checkpoint->save();
        }
    }

    /**
     * Where a scanned code puts the visitor on the mall's published map.
     *
     * @return array{checkpoint: Checkpoint, map: MallMap, anchor: MapCheckpointAnchor}
     *
     * @throws CheckpointUnavailable
     */
    public function resolve(string $slug): array
    {
        $checkpoint = Checkpoint::query()->where('public_slug', $slug)->first();
        if ($checkpoint === null) {
            throw new CheckpointUnavailable('unknown', 'This QR code is not known.', 404);
        }
        if (! $checkpoint->isActive()) {
            throw new CheckpointUnavailable('retired', 'This QR code is no longer in use. Scan another code or choose your start on the map.', 410);
        }

        $map = MallMap::publishedFor($checkpoint->mall_ref);
        if ($map === null) {
            throw new CheckpointUnavailable('no_map', 'This mall has no published map.', 404);
        }

        $anchor = $map->checkpointAnchors()->with('node')->where('checkpoint_code', $checkpoint->checkpoint_code)->first();
        if ($anchor === null) {
            throw new CheckpointUnavailable('not_on_map', 'This QR code is not on the current map. Scan another code or choose your start on the map.', 409);
        }

        return ['checkpoint' => $checkpoint, 'map' => $map, 'anchor' => $anchor];
    }

    public function retire(Checkpoint $checkpoint): void
    {
        $checkpoint->forceFill(['status' => 'retired', 'retired_at' => now()])->save();
    }

    public function reactivate(Checkpoint $checkpoint): void
    {
        $checkpoint->forceFill(['status' => 'active', 'retired_at' => null])->save();
    }

    /**
     * Give the checkpoint a new slug; codes printed with the old one stop working.
     */
    public function replaceSlug(Checkpoint $checkpoint): void
    {
        $checkpoint->forceFill(['public_slug' => $this->newSlug()])->save();
    }

    /**
     * The address printed in the QR code: a page of the Smart Mall app.
     */
    public function qrUrl(Checkpoint $checkpoint): string
    {
        return strtr((string) config('mapservice.qr_url'), ['{mall}' => rawurlencode($checkpoint->mall_ref), '{slug}' => rawurlencode($checkpoint->public_slug)]);
    }

    private function newSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(12));
        } while (Checkpoint::query()->where('public_slug', $slug)->exists());

        return $slug;
    }
}
