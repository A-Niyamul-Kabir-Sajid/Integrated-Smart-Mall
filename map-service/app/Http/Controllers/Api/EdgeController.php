<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Models\MapEdge;
use App\Services\EdgeStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Closing and reopening walkways from another system, for example a
 * maintenance desk (ability graph:write).
 */
class EdgeController extends Controller
{
    public function __construct(private EdgeStatusService $edges) {}

    public function closed(MallMap $mallMap): JsonResponse
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');

        return response()->json(['data' => $this->edges->closed($mallMap)->map(fn (MapEdge $edge): array => $this->present($edge))->values()]);
    }

    public function update(Request $request, MallMap $mallMap): JsonResponse
    {
        abort_unless($mallMap->isPublished(), 404, 'Map not found.');
        $data = $request->validate([
            'from' => ['required', 'string', 'max:80'],
            'to' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:open,closed'],
            'reason' => ['nullable', 'string', 'max:200'],
            'both_ways' => ['nullable', 'boolean'],
        ]);

        try {
            $changed = $this->edges->set($mallMap, $data['from'], $data['to'], $data['status'], $data['reason'] ?? null, $request->boolean('both_ways', true));
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage(), 'errors' => ['to' => [$error->getMessage()]]], 422);
        }

        return response()->json(['data' => $changed->load(['fromNode', 'toNode', 'connector'])->map(fn (MapEdge $edge): array => $this->present($edge))->values()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MapEdge $edge): array
    {
        return [
            'from' => $edge->fromNode->node_code,
            'to' => $edge->toNode->node_code,
            'connector' => $edge->connector?->name,
            'status' => $edge->status,
            'reason' => $edge->closed_reason,
            'changed_at' => $edge->status_changed_at?->toIso8601String(),
        ];
    }
}
