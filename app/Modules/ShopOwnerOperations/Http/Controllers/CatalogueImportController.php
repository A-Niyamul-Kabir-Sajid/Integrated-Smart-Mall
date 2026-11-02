<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Services\CatalogueImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Two-step CSV import: upload for a validated preview, then confirm. The
 * previewed rows wait in the cache under a single-use token bound to the
 * user and the shop.
 */
class CatalogueImportController extends Controller
{
    private const TTL_MINUTES = 30;

    public function create(Shop $shop): View
    {
        return view('shop-owner.catalogue.import', ['shop' => $shop, 'columns' => CatalogueImportService::COLUMNS]);
    }

    public function preview(Request $request, Shop $shop, CatalogueImportService $import): View
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);

        $result = $import->preview($shop, $request->file('file')->getRealPath());
        $token = null;

        if ($result['errors'] === []) {
            $token = (string) Str::uuid();
            Cache::put($this->cacheKey($token), [
                'user_id' => $request->user()->getKey(),
                'shop_id' => $shop->getKey(),
                'rows' => $result['rows'],
            ], now()->addMinutes(self::TTL_MINUTES));
        }

        return view('shop-owner.catalogue.import', [
            'shop' => $shop,
            'columns' => CatalogueImportService::COLUMNS,
            'rows' => $result['rows'],
            'rowErrors' => $result['errors'],
            'token' => $token,
        ]);
    }

    public function store(Request $request, Shop $shop, CatalogueImportService $import): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $pending = Cache::pull($this->cacheKey($token));

        if ($pending === null || $pending['user_id'] !== $request->user()->getKey() || $pending['shop_id'] !== $shop->getKey()) {
            return redirect()->route('workspace.catalogue.import.create', $shop)
                ->withErrors(['file' => __('This preview has expired or belongs to someone else. Upload the file again.')]);
        }

        $count = $import->import($shop, $pending['rows']);

        return redirect()->route('workspace.catalogue.index', $shop)
            ->with('status', trans_choice(':count product was imported.|:count products were imported.', $count, ['count' => $count]));
    }

    private function cacheKey(string $token): string
    {
        return 'catalogue-import:'.$token;
    }
}
