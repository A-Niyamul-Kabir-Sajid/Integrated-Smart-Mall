<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Services\CheckpointRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * QR checkpoints of every mall: the printable codes, whether each is on the
 * mall's published map, and retiring, reactivating or replacing a code.
 */
class CheckpointController extends Controller
{
    public function __construct(private CheckpointRegistry $registry) {}

    public function index(): View
    {
        $published = MallMap::query()->where('status', 'published')->whereNotNull('mall_ref')
            ->with('checkpointAnchors.node')->get()->keyBy('mall_ref');

        $rows = Checkpoint::query()->orderBy('mall_ref')->orderBy('checkpoint_code')->get()
            ->map(function (Checkpoint $checkpoint) use ($published): array {
                $anchor = $published->get($checkpoint->mall_ref)?->checkpointAnchors->firstWhere('checkpoint_code', $checkpoint->checkpoint_code);

                return [
                    'checkpoint' => $checkpoint,
                    'floor_code' => $anchor?->node->floor_code,
                    'on_map' => $anchor !== null,
                    'url' => $this->registry->qrUrl($checkpoint),
                ];
            });

        return view('admin.checkpoints.index', ['rows' => $rows->groupBy(fn (array $row): string => $row['checkpoint']->mall_ref)]);
    }

    public function retire(Checkpoint $checkpoint): RedirectResponse
    {
        $this->registry->retire($checkpoint);

        return back()->with('status', "QR code {$checkpoint->checkpoint_code} is retired; scanning it now says so.");
    }

    public function reactivate(Checkpoint $checkpoint): RedirectResponse
    {
        $this->registry->reactivate($checkpoint);

        return back()->with('status', "QR code {$checkpoint->checkpoint_code} works again.");
    }

    public function replace(Checkpoint $checkpoint): RedirectResponse
    {
        $this->registry->replaceSlug($checkpoint);

        return back()->with('status', "QR code {$checkpoint->checkpoint_code} has a new address. Print it again: the old code no longer works.");
    }
}
