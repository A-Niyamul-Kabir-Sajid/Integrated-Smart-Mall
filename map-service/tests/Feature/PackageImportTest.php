<?php

namespace Tests\Feature;

use App\Models\MallMap;
use App\Services\PackageImporter;
use Database\Factories\MallMapFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class PackageImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_package_folder_is_imported_as_a_draft_with_its_files(): void
    {
        $map = app(PackageImporter::class)->import($this->packageFolder(), 'Sample floor');

        $this->assertSame('draft', $map->status);
        $this->assertSame(['G'], $map->floor_codes);
        $this->assertSame(2, $map->stats['nodes']);
        $this->assertSame('2026-11-22 09:29:52', $map->package_generated_at->format('Y-m-d H:i:s'));
        foreach (['map_package.json', 'floors/G_plan.png', 'floors/G_graph_preview.png', 'floors/G_model.glb'] as $file) {
            Storage::disk('local')->assertExists($map->storage_dir.'/'.$file);
        }
    }

    public function test_an_annotations_file_next_to_the_package_is_imported_too(): void
    {
        $folder = $this->packageFolder(annotations: [$this->annotationPayload(), $this->annotationPayload(['id' => 'ann-0002', 'kind' => 'advertisement', 'width_m' => 0.8, 'text' => 'Sale'])]);

        $map = app(PackageImporter::class)->import($folder, 'Sample floor', publish: true);

        $this->assertTrue($map->isPublished());
        $this->assertSame(['ann-0001', 'ann-0002'], $map->annotations()->pluck('code')->all());
    }

    public function test_broken_packages_are_refused_and_leave_nothing_behind(): void
    {
        $importer = app(PackageImporter::class);
        $cases = [
            'wrong format' => [['format' => 'something-else'], 'not an ism-map-package'],
            'unknown version' => [['format_version' => 2], 'Only format_version 1'],
            'outside path' => [['floors' => [array_replace(MallMapFactory::package()['floors'][0], ['model' => ['file' => '../../secret.glb']])]], 'outside its folder'],
        ];
        foreach ($cases as $label => [$overrides, $message]) {
            try {
                $importer->import($this->packageFolder($overrides), 'Broken');
                $this->fail("{$label} was imported");
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage(), $label);
            }
        }

        $folder = $this->packageFolder();
        File::delete($folder.'/floors/G_model.glb');
        try {
            $importer->import($folder, 'Missing file');
            $this->fail('a package with a missing file was imported');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('floors/G_model.glb but the file is missing', $error->getMessage());
        }

        $this->assertDatabaseCount('mall_maps', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_failed_annotations_file_rolls_the_import_back(): void
    {
        $folder = $this->packageFolder(annotations: [$this->annotationPayload(['background' => 'not-a-colour'])]);

        try {
            app(PackageImporter::class)->import($folder, 'Bad annotations');
            $this->fail('invalid annotations were imported');
        } catch (ValidationException) {
            $this->assertDatabaseCount('mall_maps', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_the_import_command_imports_and_publishes(): void
    {
        $this->assertSame(0, Artisan::call('map:import', ['folder' => $this->packageFolder(), '--name' => 'From the command line', '--publish' => true]));

        $this->assertStringContainsString('Imported “From the command line”', Artisan::output());
        $this->assertTrue(MallMap::query()->sole()->isPublished());
        $this->assertSame(1, Artisan::call('map:import', ['folder' => sys_get_temp_dir().'/no-such-package-folder']));
    }

    public function test_only_folders_inside_the_import_root_can_be_chosen(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ism-root-'.uniqid();
        $this->packageFolder(root: $root);
        config(['mapservice.import_root' => $root]);
        $importer = app(PackageImporter::class);

        $this->assertSame(['sample/package'], $importer->availablePackages());
        $this->assertStringEndsWith('package', $importer->resolveFromImportRoot('sample/package'));

        $this->expectException(InvalidArgumentException::class);
        $importer->resolveFromImportRoot('../');
    }
}
