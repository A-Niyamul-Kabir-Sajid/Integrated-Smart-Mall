<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\MallMap;
use App\Services\AnnotationStore;
use App\Services\MapPublisher;
use App\Services\PackageImporter;
use App\Services\PackagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Administrators import map packages, publish them to the API and edit their
 * signs and advertisements in the 3D viewer.
 */
class MapController extends Controller
{
    public function __construct(
        private PackageImporter $importer,
        private PackagePresenter $presenter,
        private AnnotationStore $store,
        private MapPublisher $publisher,
    ) {}

    public function index(): View
    {
        return view('admin.maps.index', [
            'maps' => MallMap::query()->withCount(['annotations', 'nodes', 'edges'])->latest()->get(),
            'packages' => $this->importer->availablePackages(),
            'importRoot' => config('mapservice.import_root'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string', 'max:300'],
            'name' => ['required', 'string', 'max:120'],
            'mall_ref' => ['nullable', 'string', 'max:100', 'regex:'.MapPublisher::MALL_REF_PATTERN],
            'publish' => ['nullable', 'boolean'],
        ], ['mall_ref.regex' => MapPublisher::MALL_REF_MESSAGE]);

        try {
            $map = $this->importer->import($this->importer->resolveFromImportRoot($data['folder']), $data['name'], (bool) ($data['publish'] ?? false), $data['mall_ref'] ?? null);
        } catch (InvalidArgumentException $error) {
            return back()->withErrors(['folder' => $error->getMessage()])->withInput();
        }

        return redirect()->route('admin.maps.index')->with('status', "Imported “{$map->name}”.");
    }

    public function show(MallMap $mallMap): RedirectResponse
    {
        return redirect('/viewer/index.html?map='.$mallMap->id);
    }

    public function package(MallMap $mallMap): JsonResponse
    {
        return response()->json($this->presenter->package($mallMap));
    }

    public function annotations(MallMap $mallMap): JsonResponse
    {
        return response()->json($this->document($mallMap));
    }

    public function saveAnnotations(Request $request, MallMap $mallMap): JsonResponse
    {
        $this->store->replaceAll($mallMap, $request->input('annotations', []));

        return response()->json($this->document($mallMap->refresh()));
    }

    public function status(Request $request, MallMap $mallMap): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published'])]]);
        if ($data['status'] === 'published') {
            $this->publisher->publish($mallMap);
        } else {
            $this->publisher->unpublish($mallMap);
        }

        return back()->with('status', $data['status'] === 'published' ? "“{$mallMap->name}” is now visible through the API." : "“{$mallMap->name}” is hidden from the API.");
    }

    /**
     * Link the map to a Smart Mall app mall by its slug, or unlink it.
     */
    public function mall(Request $request, MallMap $mallMap): RedirectResponse
    {
        $data = $request->validate(
            ['mall_ref' => ['nullable', 'string', 'max:100', 'regex:'.MapPublisher::MALL_REF_PATTERN]],
            ['mall_ref.regex' => MapPublisher::MALL_REF_MESSAGE],
        );
        $this->publisher->assignMall($mallMap, $data['mall_ref'] ?? null);

        return back()->with('status', $mallMap->mall_ref === null ? "“{$mallMap->name}” belongs to no mall." : "“{$mallMap->name}” belongs to mall {$mallMap->mall_ref}.");
    }

    public function destroy(MallMap $mallMap): RedirectResponse
    {
        Storage::disk('local')->deleteDirectory($mallMap->storage_dir);
        $mallMap->delete();

        return redirect()->route('admin.maps.index')->with('status', "Deleted “{$mallMap->name}”.");
    }

    /**
     * @return array<string, mixed>
     */
    private function document(MallMap $mallMap): array
    {
        return [
            'format' => 'ism-map-annotations',
            'format_version' => 1,
            'coordinate_frame' => 'ism-mall-local-v1 (same as map_package.json)',
            'map_id' => $mallMap->id,
            'annotations' => $mallMap->annotations()->with('mallMap')->get()
                ->map(fn (Annotation $annotation): array => $this->presenter->annotation($annotation))->values(),
        ];
    }
}
