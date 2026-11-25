<?php

namespace App\Console\Commands;

use App\Services\PackageImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('map:import {folder : Folder that contains map_package.json} {--name= : Name shown in the admin and the API} {--mall= : Slug of the Smart Mall app mall this map belongs to} {--publish : Make it visible through the API at once}')]
#[Description('Import a map package exported by tools/blender/ism_map.py')]
class ImportMapPackage extends Command
{
    public function handle(PackageImporter $importer): int
    {
        $folder = (string) $this->argument('folder');
        $name = (string) ($this->option('name') ?: basename(dirname(rtrim($folder, '/\\'))));

        try {
            $map = $importer->import($folder, $name, (bool) $this->option('publish'), $this->option('mall') ?: null);
        } catch (InvalidArgumentException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported “{$map->name}” as {$map->id} ({$map->status}).");
        $this->line('Floors: '.implode(', ', $map->floor_codes).' · graph: '.$map->nodes()->count().' nodes, '.$map->edges()->count().' edges · signs and advertisements: '.$map->annotations()->count());

        return self::SUCCESS;
    }
}
