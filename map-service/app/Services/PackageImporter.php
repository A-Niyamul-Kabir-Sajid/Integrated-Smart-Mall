<?php

namespace App\Services;

use App\Models\MallMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports a map package folder written by tools/blender/ism_map.py export:
 * map_package.json and every floor file it names, copied into private storage.
 */
class PackageImporter
{
    public function __construct(private AnnotationStore $annotations) {}

    /**
     * Folders under the configured import root that contain a map_package.json,
     * relative to that root, newest first.
     *
     * @return list<string>
     */
    public function availablePackages(): array
    {
        $root = realpath((string) config('mapservice.import_root'));
        if ($root === false) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $iterator->setMaxDepth(4);
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'map_package.json') {
                $found[$file->getMTime().'|'.$file->getPath()] = str_replace('\\', '/', substr($file->getPath(), strlen($root) + 1));
            }
        }
        krsort($found);

        return array_values($found);
    }

    /**
     * Resolve a folder chosen from availablePackages() to an absolute path inside the import root.
     */
    public function resolveFromImportRoot(string $relative): string
    {
        $root = realpath((string) config('mapservice.import_root'));
        $path = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.$relative);
        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path.DIRECTORY_SEPARATOR.'map_package.json')) {
            throw new InvalidArgumentException('Choose a package folder from the list.');
        }

        return $path;
    }

    /**
     * Import the package in $folder. An annotations.json next to it is imported too.
     */
    public function import(string $folder, string $name, bool $publish = false): MallMap
    {
        $folder = rtrim($folder, '/\\');
        $manifestPath = $folder.DIRECTORY_SEPARATOR.'map_package.json';
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("No map_package.json in {$folder}.");
        }

        $package = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($package) || ($package['format'] ?? null) !== 'ism-map-package') {
            throw new InvalidArgumentException('map_package.json is not an ism-map-package file.');
        }
        if (($package['format_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Only format_version 1 is supported.');
        }
        if (empty($package['floors']) || ! is_array($package['floors'])) {
            throw new InvalidArgumentException('The package has no floors.');
        }

        $files = $this->referencedFiles($package);
        foreach ($files as $file) {
            if (! is_file($folder.DIRECTORY_SEPARATOR.$file)) {
                throw new InvalidArgumentException("The package names {$file} but the file is missing.");
            }
        }

        $id = (string) Str::uuid();
        $storageDir = 'maps/'.$id;
        $disk = Storage::disk('local');

        try {
            $disk->put($storageDir.'/map_package.json', (string) file_get_contents($manifestPath));
            foreach ($files as $file) {
                $disk->put($storageDir.'/'.$file, (string) file_get_contents($folder.DIRECTORY_SEPARATOR.$file));
            }

            return DB::transaction(function () use ($id, $name, $publish, $package, $storageDir, $folder): MallMap {
                $map = new MallMap([
                    'name' => $name,
                    'status' => $publish ? 'published' : 'draft',
                    'format_version' => $package['format_version'],
                    'package_generated_at' => $package['generated_at'] ?? null,
                    'storage_dir' => $storageDir,
                    'stats' => $package['stats'] ?? [],
                    'floor_codes' => array_column($package['floors'], 'floor_code'),
                ]);
                $map->id = $id;
                $map->save();

                $annotationsFile = $folder.DIRECTORY_SEPARATOR.'annotations.json';
                if (is_file($annotationsFile)) {
                    $document = json_decode((string) file_get_contents($annotationsFile), true);
                    $this->annotations->replaceAll($map, $document['annotations'] ?? []);
                }

                return $map;
            });
        } catch (\Throwable $error) {
            $disk->deleteDirectory($storageDir);

            throw $error;
        }
    }

    /**
     * Every floor file the package names, checked to stay inside the package folder.
     *
     * @param  array<string, mixed>  $package
     * @return list<string>
     */
    public function referencedFiles(array $package): array
    {
        $files = [];
        foreach ($package['floors'] as $floor) {
            foreach ([$floor['plan']['file'] ?? null, $floor['graph_preview_file'] ?? null, $floor['model']['file'] ?? null] as $file) {
                if ($file === null) {
                    continue;
                }
                if (! is_string($file) || ! preg_match('#^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)*$#', $file) || str_contains($file, '..')) {
                    throw new InvalidArgumentException('The package names a file outside its folder.');
                }
                $files[] = $file;
            }
        }

        return array_values(array_unique($files));
    }
}
