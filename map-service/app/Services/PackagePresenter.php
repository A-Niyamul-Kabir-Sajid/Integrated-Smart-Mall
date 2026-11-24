<?php

namespace App\Services;

use App\Models\Annotation;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\MapCheckpointAnchor;
use App\Models\MapNode;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Turns stored maps and annotations into API responses. Floor files and
 * images are linked with temporary signed URLs, so a browser can load them
 * without ever holding an API key.
 */
class PackagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(MallMap $map): array
    {
        return [
            'id' => $map->id,
            'name' => $map->name,
            'status' => $map->status,
            'format_version' => $map->format_version,
            'package_generated_at' => $map->package_generated_at?->toIso8601String(),
            'floor_codes' => $map->floor_codes,
            'stats' => $map->stats,
            'annotations_count' => $map->annotations_count ?? $map->annotations()->count(),
            'updated_at' => $map->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The full map package with a signed URL for every floor file.
     *
     * @return array<string, mixed>
     */
    public function package(MallMap $map): array
    {
        $package = $map->package();
        $urls = [];
        foreach ($package['floors'] as $index => $floor) {
            foreach (['plan' => $floor['plan']['file'] ?? null, 'model' => $floor['model']['file'] ?? null] as $key => $file) {
                if ($file !== null) {
                    $package['floors'][$index][$key]['url'] = $urls[$file] = $this->fileUrl($map, $file);
                }
            }
            if (isset($floor['graph_preview_file'])) {
                $package['floors'][$index]['graph_preview_url'] = $urls[$floor['graph_preview_file']] = $this->fileUrl($map, $floor['graph_preview_file']);
            }
        }
        $package['file_urls'] = $urls;
        $package['urls_expire_at'] = now()->addMinutes($this->lifetime())->toIso8601String();
        $package['map'] = $this->summary($map);

        return $package;
    }

    /**
     * One annotation in the same shape as the viewer's annotations.json.
     *
     * @return array<string, mixed>
     */
    public function annotation(Annotation $annotation): array
    {
        return [
            'id' => $annotation->code,
            'kind' => $annotation->kind,
            'floor_code' => $annotation->floor_code,
            'unit_code' => $annotation->unit_code,
            'text' => $annotation->text,
            'subtitle' => $annotation->subtitle,
            'background' => $annotation->background,
            'color' => $annotation->color,
            'image' => $annotation->image_path === null ? null : $this->fileUrl($annotation->mallMap, Str::after($annotation->image_path, $annotation->mallMap->storage_dir.'/')),
            'width_m' => $annotation->width_m,
            'lift_m' => $annotation->lift_m,
            'mount' => $annotation->mount,
            'position' => ['x' => $annotation->x_m, 'y' => $annotation->y_m, 'z' => $annotation->z_m],
            'yaw_deg' => $annotation->yaw_deg,
            'created_at' => $annotation->created_at?->toIso8601String(),
            'updated_at' => $annotation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * A resolved QR checkpoint: the code, where it hangs and where the
     * visitor stands (the start node of a route).
     *
     * @return array<string, mixed>
     */
    public function checkpoint(Checkpoint $checkpoint, MapCheckpointAnchor $anchor, MallMap $map): array
    {
        return [
            'slug' => $checkpoint->public_slug,
            'checkpoint_code' => $checkpoint->checkpoint_code,
            'label' => $checkpoint->label,
            'mall_ref' => $checkpoint->mall_ref,
            'map_id' => $map->id,
            'floor_code' => $anchor->node->floor_code,
            'node' => $this->node($anchor->node),
            'qr' => $anchor->qr,
            'start' => 'checkpoint:'.$checkpoint->public_slug,
        ];
    }

    /**
     * @return array{node_code: string, floor_code: string, node_type: string, position: array{x: float, y: float, z: float}}
     */
    public function node(MapNode $node): array
    {
        return [
            'node_code' => $node->node_code,
            'floor_code' => $node->floor_code,
            'node_type' => $node->node_type,
            'position' => ['x' => $node->x_m, 'y' => $node->y_m, 'z' => $node->z_m],
        ];
    }

    public function fileUrl(MallMap $map, string $file): string
    {
        return URL::temporarySignedRoute('files.show', now()->addMinutes($this->lifetime()), ['mallMap' => $map->id, 'path' => $file]);
    }

    private function lifetime(): int
    {
        return (int) config('mapservice.signed_url_minutes', 120);
    }
}
