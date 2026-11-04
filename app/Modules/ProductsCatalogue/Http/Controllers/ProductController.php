<?php

namespace App\Modules\ProductsCatalogue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Services\ProductDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer product pages: search, product offers across shops and comparison.
 */
class ProductController extends Controller
{
    public const MAX_COMPARED = 4;

    public function index(Request $request, Mall $publicMall, CatalogueLookup $catalogue): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'shop' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('min_price'), ['gte:min_price'])],
            'available_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(ProductDiscoveryService::SORTS)],
        ]);

        return view('customer.products.index', [
            'mall' => $publicMall,
            'products' => $catalogue->searchProducts($publicMall, [...$filters, 'available_only' => $request->boolean('available_only')]),
            'filters' => $filters,
            'categories' => ProductCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Mall $publicMall, Product $publicProduct, CatalogueLookup $catalogue): View
    {
        $offers = $catalogue->offersFor($publicMall, $publicProduct);

        abort_if($offers->isEmpty(), 404);

        return view('customer.products.show', [
            'mall' => $publicMall,
            'product' => $publicProduct->load(['category', 'images']),
            'offers' => $offers,
        ]);
    }

    public function compare(Request $request, Mall $publicMall, CatalogueLookup $catalogue): View
    {
        $ids = $request->validate([
            'listings' => ['required', 'array', 'min:1', 'max:'.self::MAX_COMPARED],
            'listings.*' => ['uuid'],
        ])['listings'];

        return view('customer.products.compare', [
            'mall' => $publicMall,
            'listings' => collect(array_unique($ids))
                ->map(fn (string $id) => $catalogue->visibleListing($publicMall, $id))
                ->filter()
                ->values(),
        ]);
    }
}
