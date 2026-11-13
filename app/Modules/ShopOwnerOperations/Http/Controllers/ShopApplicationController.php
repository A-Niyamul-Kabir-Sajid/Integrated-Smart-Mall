<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\ShopOwnerOperations\Services\ShopApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A signed-in customer applies to open a shop and follows the decision. Everything
 * here concerns the signed-in account's own applications.
 */
class ShopApplicationController extends Controller
{
    public function index(Request $request, ShopApplicationService $applications): View
    {
        return view('account.shop-applications.index', [
            'applications' => $applications->listForApplicant($request->user()),
        ]);
    }

    public function create(): View
    {
        return view('account.shop-applications.create', [
            'malls' => Mall::query()->active()->orderBy('name')->get(),
            'categories' => ShopCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ShopApplicationService $applications): RedirectResponse
    {
        $data = $request->validate([
            'mall_id' => ['required', 'uuid'],
            'shop_category_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $mall = Mall::query()->active()->findOrFail($data['mall_id']);
        $category = ShopCategory::query()->findOrFail($data['shop_category_id']);

        $applications->apply($request->user(), $mall, $category, $data);

        return redirect()->route('shop-applications.index')->with('status', __('Your application was sent to :mall. You will be notified of the decision.', ['mall' => $mall->name]));
    }
}
