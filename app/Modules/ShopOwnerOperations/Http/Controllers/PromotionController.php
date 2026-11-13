<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\DiscountType;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use App\Modules\ShopOwnerOperations\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's promotions and coupons. Routes require the promotions area for the shop in
 * the URL, and a promotion of another shop is not found through it.
 */
class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotions) {}

    public function index(Request $request, Shop $shop): View
    {
        return view('shop-owner.promotions.index', ['shop' => $shop, 'promotions' => $this->promotions->listForShop($request->user(), $shop)]);
    }

    public function create(Shop $shop): View
    {
        return view('shop-owner.promotions.form', $this->formData($shop, new ShopPromotion([
            'discount_type' => DiscountType::Percent,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'per_customer_limit' => 1,
            'min_subtotal' => 0,
        ])));
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $promotion = $this->promotions->create($request->user(), $shop, $this->validated($request));

        return redirect()->route('workspace.promotions.index', $shop)->with('status', __(':title was created.', ['title' => $promotion->title]));
    }

    public function edit(Shop $shop, ShopPromotion $shopPromotion): View
    {
        return view('shop-owner.promotions.form', $this->formData($shop, $shopPromotion));
    }

    public function update(Request $request, Shop $shop, ShopPromotion $shopPromotion): RedirectResponse
    {
        $this->promotions->update($request->user(), $shopPromotion->setRelation('shop', $shop), $this->validated($request));

        return redirect()->route('workspace.promotions.index', $shop)->with('status', __('The promotion was saved.'));
    }

    public function pause(Request $request, Shop $shop, ShopPromotion $shopPromotion): RedirectResponse
    {
        $this->promotions->pause($request->user(), $shopPromotion->setRelation('shop', $shop));

        return back()->with('status', __('The promotion is paused.'));
    }

    public function resume(Request $request, Shop $shop, ShopPromotion $shopPromotion): RedirectResponse
    {
        $this->promotions->resume($request->user(), $shopPromotion->setRelation('shop', $shop));

        return back()->with('status', __('The promotion is running again.'));
    }

    public function end(Request $request, Shop $shop, ShopPromotion $shopPromotion): RedirectResponse
    {
        $this->promotions->end($request->user(), $shopPromotion->setRelation('shop', $shop));

        return back()->with('status', __('The promotion ended.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric'],
            'coupon_code' => ['nullable', 'string', 'max:30'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Shop $shop, ShopPromotion $promotion): array
    {
        return ['shop' => $shop->loadMissing('mall'), 'promotion' => $promotion, 'types' => DiscountType::cases()];
    }
}
