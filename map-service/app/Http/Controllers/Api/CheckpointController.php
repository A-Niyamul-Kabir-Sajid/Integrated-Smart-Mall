<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CheckpointUnavailable;
use App\Http\Controllers\Controller;
use App\Services\CheckpointRegistry;
use App\Services\PackagePresenter;
use Illuminate\Http\JsonResponse;

/**
 * Resolves a scanned QR code to the start point on the mall's published map
 * (ability maps:read).
 */
class CheckpointController extends Controller
{
    public function __construct(private CheckpointRegistry $registry, private PackagePresenter $presenter) {}

    public function show(string $slug): JsonResponse
    {
        try {
            $found = $this->registry->resolve($slug);
        } catch (CheckpointUnavailable $error) {
            return response()->json(['message' => $error->getMessage(), 'reason' => $error->reason], $error->status);
        }

        return response()->json(['data' => $this->presenter->checkpoint($found['checkpoint'], $found['anchor'], $found['map'])]);
    }
}
