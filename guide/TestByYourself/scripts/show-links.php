<?php

/*
 * Prints every full URL and id the manual guide files refer to, for the data that is
 * in the database right now (ids are random and change after migrate:fresh --seed).
 * Read-only: it changes nothing.
 *
 *   php artisan tinker guide/TestByYourself/scripts/show-links.php
 *
 * Optional environment variable: BASE_URL (default http://127.0.0.1:8000).
 */

use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Models\Review;

$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$mall = Mall::query()->where('slug', 'niagara-city-mall')->firstOrFail();
$mallUrl = "{$base}/malls/{$mall->slug}";

echo "\n== MALL ==\n";
echo "Mall id          : {$mall->getKey()}\n";
echo "Mall home        : {$mallUrl}\n";
echo "Shop directory   : {$mallUrl}/shops\n";
echo "Products         : {$mallUrl}/products\n";
echo "Moderation desk  : {$base}/admin/malls/{$mall->getKey()}/moderation   (mall.admin@demo.test)\n";

echo "\n== SHOPS ==\n";
Shop::query()->whereBelongsTo($mall)->orderBy('name')->get()->each(function (Shop $shop) use ($base, $mallUrl): void {
    echo "\n{$shop->name}  [{$shop->status->value}]  id {$shop->getKey()}\n";
    echo "  page            : {$mallUrl}/shops/{$shop->slug}\n";
    echo "  shop reviews    : {$mallUrl}/shops/{$shop->slug}/reviews\n";
    echo "  workspace       : {$base}/workspace/shops/{$shop->getKey()}/catalogue\n";
    echo "  CSV import      : {$base}/workspace/shops/{$shop->getKey()}/catalogue/import\n";
    echo "  review inbox    : {$base}/workspace/shops/{$shop->getKey()}/reviews\n";
});

echo "\n== LISTINGS (product reviews) ==\n";
ShopProduct::query()->active()->with(['shop', 'variant.product'])
    ->whereIn('shop_id', Shop::query()->whereBelongsTo($mall)->select('shop_id'))
    ->get()
    ->sortBy(fn (ShopProduct $listing) => $listing->shop->name.$listing->variant->product->name.$listing->variant->label)
    ->each(fn (ShopProduct $listing) => printf(
        "%s | %s (%s) | %s | %s\n",
        $listing->shop->name,
        $listing->variant->product->name,
        $listing->variant->label,
        $listing->getKey(),
        "{$mallUrl}/listings/{$listing->getKey()}/reviews",
    ));

echo "\n== PRODUCT PAGES ==\n";
Product::query()->orderBy('name')->get()->each(fn (Product $product) => print ("{$product->name} : {$mallUrl}/products/{$product->getKey()}\n"));

echo "\n== REVIEWS THAT EXIST (ids for the reply and report checks) ==\n";
$reviews = Review::query()->with(['shop', 'author', 'listing.variant.product'])->latest()->limit(30)->get();

if ($reviews->isEmpty()) {
    echo "(none yet)\n";
}

$reviews->each(fn (Review $review) => printf(
    "%s | %s | %s | %d stars | by %s | %s\n",
    $review->getKey(),
    $review->shop->name,
    $review->isShopReview() ? 'shop review' : $review->listing->variant->product->name,
    $review->rating,
    $review->author->email ?? '?',
    $review->status->value,
));
echo "\n";
