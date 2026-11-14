<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\ActivityLogService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit trail of one mall. Routes require administer-mall for the mall in the URL.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request, Mall $mall, ActivityLogService $log): View
    {
        $filters = $request->validate(['area' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/']]);

        return view('mall-admin.activity.index', [
            'mall' => $mall,
            'entries' => $log->forMall($request->user(), $mall, $filters),
            'areas' => $log->areas($request->user(), $mall),
            'area' => $filters['area'] ?? null,
        ]);
    }
}
