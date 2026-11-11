<?php

namespace App\Modules\FavouritesPreferences\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FavouritesPreferences\Services\FavouriteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in customer's saved shops and saved listings. Every action works
 * on the current account only; there is no user id to tamper with.
 */
class FavouriteController extends Controller
{
    public function index(Request $request, FavouriteService $favourites): View
    {
        $userId = $request->user()->getKey();

        return view('account.saved', [
            'savedShops' => $favourites->savedShops($userId),
            'savedListings' => $favourites->savedListings($userId),
        ]);
    }

    public function storeShop(Request $request, string $shop, FavouriteService $favourites): RedirectResponse
    {
        $target = $favourites->saveableShop($shop);

        abort_if($target === null, 404);

        $favourites->saveShop($request->user()->getKey(), $target);

        return back()->with('status', __(':name was saved.', ['name' => $target->name]));
    }

    public function destroyShop(Request $request, string $shop, FavouriteService $favourites): RedirectResponse
    {
        $favourites->removeShop($request->user()->getKey(), $shop);

        return back()->with('status', __('Removed from your saved items.'));
    }

    public function storeListing(Request $request, string $listing, FavouriteService $favourites): RedirectResponse
    {
        $target = $favourites->saveableListing($listing);

        abort_if($target === null, 404);

        $favourites->saveListing($request->user()->getKey(), $target);

        return back()->with('status', __(':name was saved.', ['name' => $target->variant->product->name]));
    }

    public function destroyListing(Request $request, string $listing, FavouriteService $favourites): RedirectResponse
    {
        $favourites->removeListing($request->user()->getKey(), $listing);

        return back()->with('status', __('Removed from your saved items.'));
    }
}
