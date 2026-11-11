<?php

namespace App\Modules\FavouritesPreferences\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\FavouritesPreferences\Listeners\ApplyPreferredLocale;
use App\Modules\FavouritesPreferences\Services\FavouriteService;
use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use App\Modules\FavouritesPreferences\Services\UserNotificationPreferences;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;

/**
 * Registers FavouritesPreferences: account preferences and favourites.
 * Listed after SharedPlatform in config/modules.php so its notification
 * preferences replace the platform defaults.
 */
class FavouritesPreferencesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        Login::class => [ApplyPreferredLocale::class],
    ];

    public function register(): void
    {
        $this->app->bind(NotificationPreferences::class, UserNotificationPreferences::class);
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('favourites', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        $this->showSaveButtonsOnDirectoryAndCataloguePages();
    }

    /**
     * Tells the shop and product pages (owned by other modules) whether the visitor
     * has saved what they show. Those pages render the save buttons only when this
     * data is present; guests get `favouritesEnabled` without saved state.
     */
    private function showSaveButtonsOnDirectoryAndCataloguePages(): void
    {
        View::composer('customer.shops.show', function (ViewInstance $view): void {
            $shop = $view->getData()['shop'] ?? null;
            $user = auth()->user();

            if ($shop !== null) {
                $view->with('favouritesEnabled', true)
                    ->with('shopIsSaved', $user !== null && app(FavouriteService::class)->hasSavedShop($user->getKey(), $shop->getKey()));
            }
        });

        View::composer('customer.products.show', function (ViewInstance $view): void {
            $offers = $view->getData()['offers'] ?? null;
            $user = auth()->user();

            $mall = $view->getData()['mall'] ?? null;

            if ($offers !== null) {
                $view->with('favouritesEnabled', true)
                    ->with('savedListingIds', $user === null ? [] : app(FavouriteService::class)
                        ->savedListingIds($user->getKey(), $offers->pluck('shop_product_id')->all()))
                    ->with('shoppingLists', $user === null || $mall === null ? collect() : app(ShoppingListService::class)
                        ->listsFor($user->getKey(), $mall->getKey()));
            }
        });
    }
}
