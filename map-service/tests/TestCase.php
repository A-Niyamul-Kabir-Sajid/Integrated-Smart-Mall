<?php

namespace Tests;

use App\Models\ApiKey;
use App\Services\ApiKeyIssuer;
use Database\Factories\MallMapFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $temporaryFolders = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFolders as $folder) {
            File::deleteDirectory($folder);
        }
        parent::tearDown();
    }

    /**
     * A package folder like the Blender export writes, in a temporary directory.
     *
     * @param  array<string, mixed>  $overrides  merged into map_package.json
     */
    protected function packageFolder(array $overrides = [], ?array $annotations = null, ?string $root = null): string
    {
        $root ??= sys_get_temp_dir().DIRECTORY_SEPARATOR.'ism-map-test-'.Str::random(8);
        $this->temporaryFolders[] = $root;
        $folder = $root.DIRECTORY_SEPARATOR.'sample'.DIRECTORY_SEPARATOR.'package';
        File::ensureDirectoryExists($folder.DIRECTORY_SEPARATOR.'floors');
        File::put($folder.'/map_package.json', (string) json_encode(array_replace(MallMapFactory::package(), $overrides)));
        File::put($folder.'/floors/G_plan.png', 'plan');
        File::put($folder.'/floors/G_graph_preview.png', 'preview');
        File::put($folder.'/floors/G_model.glb', 'glTF-model');
        if ($annotations !== null) {
            File::put($folder.'/annotations.json', (string) json_encode(['format' => 'ism-map-annotations', 'format_version' => 1, 'annotations' => $annotations]));
        }

        return $folder;
    }

    /**
     * @param  list<string>  $abilities
     * @return array{0: ApiKey, 1: string}
     */
    protected function apiKey(array $abilities = ['maps:read']): array
    {
        return app(ApiKeyIssuer::class)->issue('Test app', $abilities);
    }

    /**
     * One annotation in the viewer's JSON shape.
     *
     * @return array<string, mixed>
     */
    protected function annotationPayload(array $overrides = []): array
    {
        return array_replace([
            'id' => 'ann-0001',
            'kind' => 'shop_sign',
            'floor_code' => 'G',
            'unit_code' => 'G-101',
            'text' => 'Cafe Aroma',
            'subtitle' => 'Coffee and bakery',
            'background' => '#1f2a44',
            'color' => '#ffffff',
            'image' => null,
            'width_m' => 2.4,
            'lift_m' => 0,
            'mount' => 'wall',
            'position' => ['x' => 3.8, 'y' => 6.4, 'z' => 1.5],
            'yaw_deg' => 180,
        ], $overrides);
    }

    protected const PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}
