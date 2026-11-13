<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use App\Modules\ShopOwnerOperations\Services\ShopApplicationService;
use App\Modules\ShopOwnerOperations\Services\ShopLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The mall administrators' queue of shop applications and the suspension of active
 * shops. Routes require administer-mall for the mall in the URL; an application or
 * shop of another mall is not found through it.
 */
class MallShopApplicationController extends Controller
{
    public function index(Request $request, Mall $mall, ShopApplicationService $applications): View
    {
        return view('mall-admin.shop-applications.index', [
            'mall' => $mall,
            'applications' => $applications->listForMall($request->user(), $mall),
            'shops' => $mall->shops()->whereIn('status', [ShopStatus::Active->value, ShopStatus::Closed->value])->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, Mall $mall, ShopApplication $application, ShopApplicationService $applications): RedirectResponse
    {
        $this->ensureInMall($mall, $application->mall_id);

        $applications->approve($request->user(), $application);

        return back()->with('status', __(':shop was approved and is now active.', ['shop' => $application->shop->name]));
    }

    public function reject(Request $request, Mall $mall, ShopApplication $application, ShopApplicationService $applications): RedirectResponse
    {
        $this->ensureInMall($mall, $application->mall_id);

        $applications->reject($request->user(), $application, (string) $request->input('reason'));

        return back()->with('status', __(':shop was rejected.', ['shop' => $application->shop->name]));
    }

    public function suspend(Request $request, Mall $mall, Shop $shop, ShopLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureInMall($mall, $shop->mall_id);

        $lifecycle->suspend($request->user(), $shop, (string) $request->input('reason'));

        return back()->with('status', __(':shop was suspended.', ['shop' => $shop->name]));
    }

    public function reactivate(Request $request, Mall $mall, Shop $shop, ShopLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureInMall($mall, $shop->mall_id);

        $lifecycle->reactivate($request->user(), $shop);

        return back()->with('status', __(':shop is active again.', ['shop' => $shop->name]));
    }

    private function ensureInMall(Mall $mall, string $ownerMallId): void
    {
        abort_unless($ownerMallId === $mall->getKey(), 404);
    }
}
