<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnotationRequest;
use App\Models\Annotation;
use App\Models\MallMap;
use App\Services\AnnotationStore;
use App\Services\PackagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Signs and advertisements of a published map. Reading needs maps:read;
 * creating, changing and deleting need annotations:write.
 */
class AnnotationController extends Controller
{
    public function __construct(private PackagePresenter $presenter, private AnnotationStore $store) {}

    public function index(Request $request, MallMap $mallMap): JsonResponse
    {
        $this->published($mallMap);
        $filters = $request->validate([
            'floor' => ['nullable', 'string', 'max:20'],
            'unit_code' => ['nullable', 'string', 'max:40'],
            'kind' => ['nullable', Rule::in(Annotation::KINDS)],
        ]);

        $annotations = $mallMap->annotations()
            ->with('mallMap')
            ->when($filters['floor'] ?? null, fn ($query, $floor) => $query->where('floor_code', $floor))
            ->when($filters['unit_code'] ?? null, fn ($query, $unit) => $query->where('unit_code', $unit))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->get();

        return response()->json([
            'map_id' => $mallMap->id,
            'data' => $annotations->map(fn (Annotation $a): array => $this->presenter->annotation($a))->values(),
        ]);
    }

    public function show(MallMap $mallMap, string $code): JsonResponse
    {
        return response()->json(['data' => $this->presenter->annotation($this->find($mallMap, $code))]);
    }

    public function store(AnnotationRequest $request, MallMap $mallMap): JsonResponse
    {
        $this->published($mallMap);
        $annotation = $this->store->create($mallMap, $request->validated());

        return response()->json(['data' => $this->presenter->annotation($annotation->load('mallMap'))], 201);
    }

    public function update(AnnotationRequest $request, MallMap $mallMap, string $code): JsonResponse
    {
        $annotation = $this->store->update($this->find($mallMap, $code), $request->validated());

        return response()->json(['data' => $this->presenter->annotation($annotation)]);
    }

    public function destroy(MallMap $mallMap, string $code): Response
    {
        $this->store->delete($this->find($mallMap, $code));

        return response()->noContent();
    }

    private function find(MallMap $mallMap, string $code): Annotation
    {
        $this->published($mallMap);

        return $mallMap->annotations()->with('mallMap')->where('code', $code)->firstOr(fn () => abort(404, 'Annotation not found.'));
    }

    private function published(MallMap $mallMap): void
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');
    }
}
