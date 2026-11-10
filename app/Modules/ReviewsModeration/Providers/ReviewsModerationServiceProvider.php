<?php

namespace App\Modules\ReviewsModeration\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Events\PurchaseStatusChanged;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Listeners\RevokeVerificationWhenPurchaseEnds;
use App\Modules\ReviewsModeration\Services\RatingSummaryService;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;

/**
 * Registers ReviewsModeration: reviews, ratings, shop replies, reports and moderation.
 */
class ReviewsModerationServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        PurchaseStatusChanged::class => [RevokeVerificationWhenPurchaseEnds::class],
    ];

    public function register(): void
    {
        $this->app->bind(ReviewSummaryLookup::class, RatingSummaryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('review-submissions', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // A public listing is an active listing of a listed shop in the mall named in the URL.
        Route::bind('publicListing', function (string $id, RoutingRoute $route): ShopProduct {
            abort_unless(Str::isUuid($id), 404);

            $mall = $route->parameter('publicMall');
            abort_unless($mall instanceof Mall, 404);

            return ShopProduct::query()->active()
                ->whereHas('shop', fn ($shop) => $shop->whereBelongsTo($mall)->listed())
                ->with(['shop', 'variant.product'])
                ->findOrFail($id);
        });

        $this->showRatingsOnDirectoryAndCataloguePages();
    }

    /**
     * Pushes rating summaries into the shop and product pages of other modules, so those
     * modules need no knowledge of reviews: their views just render the data when it is present.
     */
    private function showRatingsOnDirectoryAndCataloguePages(): void
    {
        View::composer('customer.shops.show', function (ViewInstance $view): void {
            $shop = $view->getData()['shop'] ?? null;

            if ($shop !== null) {
                $view->with('ratingSummary', app(ReviewSummaryLookup::class)->forShop($shop->getKey()));
            }
        });

        View::composer('account.show', function (ViewInstance $view): void {
            $user = auth()->user();

            if ($user !== null) {
                $view->with('moderatedMalls', Mall::query()->active()->orderBy('name')->limit(20)->get()
                    ->filter(fn (Mall $mall): bool => Gate::forUser($user)->allows(Ability::AdministerMall->value, $mall))->values());
            }
        });

        View::composer('customer.products.show', function (ViewInstance $view): void {
            $offers = $view->getData()['offers'] ?? null;

            if ($offers !== null) {
                $view->with('listingRatings', app(ReviewSummaryLookup::class)->forListings($offers->pluck('shop_product_id')->all()));
            }
        });
    }
}
