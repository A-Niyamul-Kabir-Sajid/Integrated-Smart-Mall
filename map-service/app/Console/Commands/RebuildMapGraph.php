<?php

namespace App\Console\Commands;

use App\Models\MallMap;
use App\Services\MapEditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('map:rebuild-graph {map? : Map id; every map when left out}')]
#[Description('Rewrite the navigation graph of stored maps from their map_package.json and replay the administrators\' edits (closures are kept)')]
class RebuildMapGraph extends Command
{
    public function handle(MapEditService $edits): int
    {
        $id = $this->argument('map');
        $maps = $id === null ? MallMap::query()->orderBy('created_at')->get() : MallMap::query()->whereKey($id)->get();
        if ($maps->isEmpty()) {
            $this->error($id === null ? 'There are no maps.' : "There is no map {$id}.");

            return self::FAILURE;
        }

        $failed = false;
        foreach ($maps as $map) {
            try {
                $edits->rebuild($map);
                $this->info("“{$map->name}”: {$map->nodes()->count()} nodes, {$map->edges()->count()} edges, {$map->checkpointAnchors()->count()} QR checkpoints.");
            } catch (InvalidArgumentException $error) {
                $failed = true;
                $this->error("“{$map->name}”: {$error->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
