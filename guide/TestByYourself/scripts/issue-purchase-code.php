<?php

/*
 * Seller-side stand-in for the screen that does not exist yet (EPIC-10).
 *
 * Records a completed sale of one listing to a buyer through PurchaseService, then
 * issues the one-time purchase code through VerificationCredentialService, exactly
 * as the real screen will. Run it with Tinker from the project root:
 *
 *   PowerShell:  $env:LISTING_ID='<listing uuid>'; php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
 *   Bash:        LISTING_ID=<listing uuid> php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
 *
 * Instead of LISTING_ID you may set PRODUCT (a product name such as "Cold Coffee")
 * and OPTION (an option label such as "Regular"; optional if the shop sells one option).
 *
 * Optional environment variables: BUYER_EMAIL (default customer@demo.test),
 * STAFF_EMAIL (default owner@demo.test), QUANTITY (default 1), BASE_URL
 * (default http://127.0.0.1:8000).
 * Without LISTING_ID or PRODUCT it lists the listings of the staff member's shop and stops.
 *
 * The code is printed once and cannot be recovered later. Only for the demo data;
 * never run it against real data.
 */

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\PurchaseVerification\Services\VerificationCredentialService;

$staff = User::query()->where('email', getenv('STAFF_EMAIL') ?: 'owner@demo.test')->firstOrFail();
$buyer = User::query()->where('email', getenv('BUYER_EMAIL') ?: 'customer@demo.test')->firstOrFail();
$listingId = getenv('LISTING_ID') ?: null;
$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8000', '/');

if ($listingId === null && getenv('PRODUCT')) {
    $matches = ShopProduct::query()->active()->with('variant.product')
        ->whereIn('shop_id', $staff->shopMemberships()->pluck('shop_id'))
        ->whereHas('variant.product', fn ($product) => $product->where('name', getenv('PRODUCT')))
        ->when(getenv('OPTION'), fn ($query) => $query->whereHas('variant', fn ($variant) => $variant->where('label', getenv('OPTION'))))
        ->get();

    if ($matches->count() !== 1) {
        echo "PRODUCT/OPTION matched {$matches->count()} listings; run without them to see the list.\n";

        return;
    }

    $listingId = $matches->first()->getKey();
}

if ($listingId === null) {
    echo "Set LISTING_ID (or PRODUCT and OPTION) for one of these listings:\n";

    ShopProduct::query()->active()->with(['shop', 'variant.product'])
        ->whereIn('shop_id', $staff->shopMemberships()->pluck('shop_id'))
        ->get()
        ->each(fn (ShopProduct $listing) => printf(
            "  %s  %s (%s) at %s\n",
            $listing->getKey(),
            $listing->variant->product->name,
            $listing->variant->label,
            $listing->shop->name,
        ));

    return;
}

$listing = ShopProduct::query()->with('shop')->findOrFail($listingId);

$purchase = app(PurchaseService::class)->recordPurchase($staff, $listing->shop, [
    'external_receipt_ref' => 'MANUAL-'.now()->format('YmdHis'),
    'buyer_user_id' => $buyer->getKey(),
    'items' => [['shop_product_id' => $listing->getKey(), 'quantity' => (int) (getenv('QUANTITY') ?: 1)]],
]);

$issued = app(VerificationCredentialService::class)->issueCredential($staff, $purchase->items->first());

echo "\nSale recorded: receipt {$purchase->external_receipt_ref} for {$buyer->email} at {$listing->shop->name}\n";
echo "Review page : {$base}/malls/{$listing->shop->mall->slug}/listings/{$listing->getKey()}/reviews\n";
echo "Purchase code (shown once): {$issued->secret}\n";
