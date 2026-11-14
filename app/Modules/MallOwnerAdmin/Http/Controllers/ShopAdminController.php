<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallOwnerAdmin\Services\TenantAdministrationService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenants of one mall. Routes require administer-mall for the mall in the URL; a shop of
 * another mall is not found through it, even by its id.
 */
class ShopAdminController extends Controller
{
    public function __construct(private TenantAdministrationService $tenants) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ShopStatus::class)],
        ]);

        return view('mall-admin.shops.index', [
            'mall' => $mall,
            'shops' => $this->tenants->list($request->user(), $mall, $filters),
            'statuses' => ShopStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Mall $mall): View
    {
        return view('mall-admin.shops.create', [
            'mall' => $mall,
            'categories' => ShopCategory::query()->orderBy('name')->get(),
            'floors' => $mall->floors()->active()->get(),
        ]);
    }

    public function store(Request $request, Mall $mall): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80'],
            'shop_category_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'floor_id' => ['nullable', 'uuid'],
            'unit_code' => ['nullable', 'string', 'max:40'],
            'area_sq_m' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'owner_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $shop = $this->tenants->create($request->user(), $mall, ShopCategory::query()->findOrFail($data['shop_category_id']), $data);

        return redirect()->route('mall-admin.shops.edit', [$mall, $shop])->with('status', __(':shop was created as a draft.', ['shop' => $shop->name]));
    }

    public function edit(Request $request, Mall $mall, Shop $shop): View
    {
        $this->ensureInMall($mall, $shop);

        return view('mall-admin.shops.edit', [
            'mall' => $mall,
            'shop' => $shop->load(['category', 'units' => fn ($units) => $units->where('status', '!=', 'archived')->with('floor:floor_id,code,name')]),
            'owners' => $this->tenants->ownersOf($request->user(), $mall, $shop),
            'categories' => ShopCategory::query()->orderBy('name')->get(),
            'floors' => $mall->floors()->active()->get(),
        ]);
    }

    public function update(Request $request, Mall $mall, Shop $shop): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'shop_category_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $this->tenants->update($request->user(), $mall, $shop, ShopCategory::query()->findOrFail($data['shop_category_id']), $data);

        return back()->with('status', __(':shop was saved.', ['shop' => $shop->name]));
    }

    public function assignUnit(Request $request, Mall $mall, Shop $shop): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $data = $request->validate([
            'floor_id' => ['required', 'uuid'],
            'unit_code' => ['required', 'string', 'max:40'],
            'area_sq_m' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $unit = $this->tenants->assignUnit($request->user(), $mall, $shop, $data['floor_id'], $data['unit_code'], filled($data['area_sq_m'] ?? null) ? (float) $data['area_sq_m'] : null);

        return back()->with('status', __('Unit :code was assigned.', ['code' => $unit->unit_code]));
    }

    public function releaseUnit(Request $request, Mall $mall, Shop $shop, ShopUnit $unit): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);
        abort_unless($unit->shop_id === $shop->getKey(), 404);

        $this->tenants->releaseUnit($request->user(), $mall, $shop, $unit);

        return back()->with('status', __('Unit :code was released.', ['code' => $unit->unit_code]));
    }

    public function activate(Request $request, Mall $mall, Shop $shop): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $this->tenants->activate($request->user(), $mall, $shop);

        return back()->with('status', __(':shop is now active.', ['shop' => $shop->name]));
    }

    public function archive(Request $request, Mall $mall, Shop $shop): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $this->tenants->archive($request->user(), $mall, $shop, (string) $request->input('reason'));

        return redirect()->route('mall-admin.shops.index', $mall)->with('status', __(':shop was archived.', ['shop' => $shop->name]));
    }

    public function addOwner(Request $request, Mall $mall, Shop $shop): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $contact = $request->validate(['contact' => ['required', 'string', 'max:255']])['contact'];
        $owner = $this->tenants->addOwner($request->user(), $mall, $shop, $contact);

        return back()->with('status', __(':name is now an owner.', ['name' => $owner->display_name]));
    }

    public function removeOwner(Request $request, Mall $mall, Shop $shop, User $owner): RedirectResponse
    {
        $this->ensureInMall($mall, $shop);

        $this->tenants->removeOwner($request->user(), $mall, $shop, $owner);

        return back()->with('status', __(':name is no longer an owner.', ['name' => $owner->display_name]));
    }

    private function ensureInMall(Mall $mall, Shop $shop): void
    {
        abort_unless($shop->mall_id === $mall->getKey(), 404);
    }
}
