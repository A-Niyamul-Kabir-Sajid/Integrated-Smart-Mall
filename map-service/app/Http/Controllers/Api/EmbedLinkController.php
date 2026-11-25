<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RouteNotPossible;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\MallMap;
use App\Services\EmbedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * POST /api/v1/embed-links: a short-lived signed link to the embedded map
 * of a mall's published map (ability maps:read), for an iframe.
 */
class EmbedLinkController extends Controller
{
    public function __construct(private EmbedService $embeds) {}

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('to'))) {
            $request->merge(['to' => [$request->input('to')]]);
        }
        $data = $request->validate([
            'mall_ref' => ['required_without:map_id', 'nullable', 'string', 'max:100'],
            'map_id' => ['required_without:mall_ref', 'nullable', 'string', 'max:40'],
            'from' => ['nullable', 'string', 'max:200'],
            'to' => ['nullable', 'array', 'max:10'],
            'to.*' => ['string', 'max:200'],
            'step_free' => ['nullable', 'boolean'],
            'cost' => ['nullable', 'in:time,distance'],
            'lang' => ['nullable', 'in:'.implode(',', EmbedService::LANGUAGES)],
            'minutes' => ['nullable', 'integer', 'between:1,60'],
        ]);

        $map = isset($data['map_id'])
            ? MallMap::query()->whereKey($data['map_id'])->where('status', 'published')->first()
            : MallMap::publishedFor((string) $data['mall_ref']);
        if ($map === null) {
            return response()->json(['message' => 'There is no published map for this mall.', 'reason' => 'no_map'], 404);
        }

        try {
            $this->embeds->assertPlaceSyntax($data['from'] ?? null, 'from');
            foreach ($data['to'] ?? [] as $index => $place) {
                $this->embeds->assertPlaceSyntax($place, "to.{$index}");
            }
            /** @var ApiKey $key */
            $key = $request->attributes->get('api_key');
            $link = $this->embeds->link($key, $map, [
                'from' => $data['from'] ?? null,
                'to' => array_values($data['to'] ?? []),
                'step_free' => $request->boolean('step_free'),
                'cost' => $data['cost'] ?? 'time',
                'lang' => $data['lang'] ?? 'en',
                'minutes' => $data['minutes'] ?? null,
            ]);
        } catch (RouteNotPossible $error) {
            return response()->json(['message' => $error->getMessage(), 'reason' => $error->reason, 'errors' => [$error->field => [$error->getMessage()]]], 422);
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage(), 'reason' => 'no_origins'], 422);
        }

        return response()->json(['data' => $link + ['map_id' => $map->id, 'mall_ref' => $map->mall_ref]], 201);
    }
}
