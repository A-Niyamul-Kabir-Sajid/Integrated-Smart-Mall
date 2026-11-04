<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ProductsCatalogue\Services\CatalogueService;
use App\Modules\ShopOwnerOperations\Http\Requests\ListingRules;
use App\Modules\ShopOwnerOperations\Http\Requests\StoreListingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's catalogue in the shop workspace. Routes require work-in-shop for
 * the shop in the URL; all writes go through ProductsCatalogue's CatalogueService.
 */
class CatalogueController extends Controller
{
    public function index(Request $request, Shop $shop): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['active', 'archived'])]])['status'] ?? 'active';

        return view('shop-owner.catalogue.index', [
            'shop' => $shop,
            'status' => $status,
            'listings' => ShopProduct::query()
                ->where('shop_id', $shop->getKey())
                ->where('status', $status)
                ->with('variant.product')
                ->orderBy('seller_sku')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Shop $shop): View
    {
        return view('shop-owner.catalogue.create', [
            'shop' => $shop,
            'categories' => ProductCategory::query()->orderBy('name')->get(),
            'variants' => ProductVariant::query()
                ->where('status', 'active')
                ->whereHas('product', fn ($product) => $product->where('status', 'active'))
                ->whereDoesntHave('listings', fn ($listing) => $listing->where('shop_id', $shop->getKey()))
                ->with('product')
                ->get()
                ->sortBy(fn (ProductVariant $variant): string => $variant->product->name.' '.$variant->label)
                ->take(300),
        ]);
    }

    public function store(StoreListingRequest $request, Shop $shop, CatalogueService $catalogue): RedirectResponse
    {
        $data = $request->validated();
        $listing = $request->only(['seller_sku', 'price_amount', 'availability_status']);

        if ($data['mode'] === 'existing') {
            $catalogue->listExistingVariant($shop, ProductVariant::query()->with('product')->findOrFail($data['variant_id']), $listing);
        } else {
            $catalogue->createProductListing(
                $shop,
                $request->only(['product_category_id', 'name', 'brand', 'description']),
                ['variant_code' => $data['variant_code'] ?? null, 'label' => $data['variant_label']],
                $listing,
            );
        }

        return redirect()->route('workspace.catalogue.index', $shop)->with('status', __('The product was added to your catalogue.'));
    }

    public function edit(Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): View
    {
        $shopListing->load('variant.product.images', 'variant.product.category');

        return view('shop-owner.catalogue.edit', [
            'shop' => $shop,
            'listing' => $shopListing,
            'product' => $shopListing->variant->product,
            'canEditProduct' => $catalogue->canEditSharedProduct($shop, $shopListing->variant->product),
            'categories' => ProductCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->updateListing($shopListing->setRelation('shop', $shop), $request->validate(ListingRules::rules(), ListingRules::messages()));

        return redirect()->route('workspace.catalogue.edit', [$shop, $shopListing])->with('status', __('Your listing was saved.'));
    }

    public function archive(Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->archiveListing($shopListing->setRelation('shop', $shop));

        return redirect()->route('workspace.catalogue.index', $shop)->with('status', __('The listing was archived.'));
    }

    public function restore(Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->restoreListing($shopListing->setRelation('shop', $shop));

        return redirect()->route('workspace.catalogue.edit', [$shop, $shopListing])->with('status', __('The listing is active again.'));
    }

    public function updateProduct(Request $request, Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): RedirectResponse
    {
        $details = $request->validate([
            'product_category_id' => ['required', 'uuid', Rule::exists('product_categories', 'product_category_id')],
            'name' => ['required', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $catalogue->updateSharedProduct($shop, $shopListing->loadMissing('variant.product')->variant->product, $details);

        return redirect()->route('workspace.catalogue.edit', [$shop, $shopListing])->with('status', __('The product details were saved.'));
    }

    public function storeImage(Request $request, Shop $shop, ShopProduct $shopListing, CatalogueService $catalogue): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt_text' => ['required', 'string', 'max:200'],
        ]);

        $catalogue->addImage($shop, $shopListing->loadMissing('variant.product')->variant->product, $validated['image'], $validated['alt_text']);

        return redirect()->route('workspace.catalogue.edit', [$shop, $shopListing])->with('status', __('The image was added.'));
    }
}
