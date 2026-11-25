<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallMap;
use App\Services\EdgeStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Administrators close a walkway, lift or escalator of a map for a while and
 * reopen it; routes avoid closed ones at once.
 */
class ClosureController extends Controller
{
    public function __construct(private EdgeStatusService $edges) {}

    public function index(MallMap $mallMap): View
    {
        return view('admin.maps.closures', [
            'map' => $mallMap,
            'closed' => $this->edges->closed($mallMap),
            'nodeCodes' => $mallMap->nodes()->orderBy('node_code')->pluck('node_code'),
        ]);
    }

    public function update(Request $request, MallMap $mallMap): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:80'],
            'to' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:open,closed'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $changed = $this->edges->set($mallMap, $data['from'], $data['to'], $data['status'], $data['reason'] ?? null, $request->boolean('both_ways', true));
        } catch (InvalidArgumentException $error) {
            return back()->withErrors(['to' => $error->getMessage()])->withInput();
        }

        $what = $changed->count() === 1 ? 'One direction' : 'Both directions';

        return back()->with('status', "{$what} between {$data['from']} and {$data['to']} ".($data['status'] === 'closed' ? 'closed; routes now go around.' : 'open again.'));
    }
}
