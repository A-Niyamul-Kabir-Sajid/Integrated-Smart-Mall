<?php

namespace App\Http\Controllers;

use App\Models\MallMap;
use App\Services\PackageImporter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a map's floor files and annotation images behind temporary signed
 * URLs (handed out by the API and the admin viewer). Any origin may load
 * them, because the signature is the permission.
 */
class FileController extends Controller
{
    private const TYPES = [
        'glb' => 'model/gltf-binary',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(private PackageImporter $importer) {}

    public function show(MallMap $mallMap, string $path): Response
    {
        abort_if(str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)*$#', $path), 404);

        $allowed = str_starts_with($path, 'images/') || in_array($path, $this->importer->referencedFiles($mallMap->package()), true);
        $full = $mallMap->storage_dir.'/'.$path;
        abort_unless($allowed && Storage::disk('local')->exists($full), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return Storage::disk('local')->response($full, basename($path), [
            'Content-Type' => self::TYPES[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
