<?php

namespace App\Modules\ProductsCatalogue\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductImage;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes to the catalogue on behalf of one shop (decision D-011): shops own
 * their listings; shared product details change only while the shop is the
 * product's sole lister. Callers authorise the shop first (work-in-shop).
 */
class CatalogueService
{
    public const CURRENCY = 'BDT';

    public function __construct(
        private DirectoryLookup $directory,
        private AuditLogger $audit,
    ) {}

    /**
     * Creates a new shared product with one variant and the shop's listing for it.
     *
     * @param  array{product_category_id: string, name: string, brand?: string|null, description?: string|null}  $product
     * @param  array{variant_code?: string|null, label: string, attributes?: array<string, string>}  $variant
     * @param  array{seller_sku: string, price_amount: string|float, availability_status: string}  $listing
     */
    public function createProductListing(Shop $shop, array $product, array $variant, array $listing): ShopProduct
    {
        $this->ensureOperational($shop);
        $this->ensureSkuFree($shop, $listing['seller_sku']);

        return DB::transaction(function () use ($shop, $product, $variant, $listing): ShopProduct {
            $created = Product::query()->create([
                'product_category_id' => $product['product_category_id'],
                'name' => trim($product['name']),
                'brand' => blank($product['brand'] ?? null) ? null : trim($product['brand']),
                'description' => $product['description'] ?? null,
                'status' => 'active',
            ]);

            $createdVariant = $created->variants()->create([
                'variant_code' => Str::slug($variant['variant_code'] ?? '') ?: 'default',
                'label' => trim($variant['label']),
                'attributes_json' => $variant['attributes'] ?? [],
                'status' => 'active',
            ]);

            $this->audit->record('product.created', $created, ['name' => $created->name], $shop->mall_id, $shop->getKey());

            return $this->storeListing($shop, $createdVariant, $listing);
        });
    }

    /**
     * Adds the shop's own listing for a variant that already exists in the shared catalogue.
     *
     * @param  array{seller_sku: string, price_amount: string|float, availability_status: string}  $listing
     */
    public function listExistingVariant(Shop $shop, ProductVariant $variant, array $listing): ShopProduct
    {
        $this->ensureOperational($shop);

        if ($variant->status !== 'active' || $variant->product->status !== 'active') {
            throw new BusinessRuleViolation('listing.variant_archived', __('This product is no longer offered.'));
        }

        if (ShopProduct::query()->where('shop_id', $shop->getKey())->where('variant_id', $variant->getKey())->exists()) {
            throw new BusinessRuleViolation('listing.already_listed', __('Your shop already lists this product option.'));
        }

        $this->ensureSkuFree($shop, $listing['seller_sku']);

        return DB::transaction(fn (): ShopProduct => $this->storeListing($shop, $variant, $listing));
    }

    /**
     * Changes the shop's own price, SKU or availability. A listing never changes shop.
     *
     * @param  array{seller_sku: string, price_amount: string|float, availability_status: string}  $changes
     */
    public function updateListing(ShopProduct $listing, array $changes): ShopProduct
    {
        $this->ensurePrice($changes['price_amount']);

        if ($changes['seller_sku'] !== $listing->seller_sku) {
            $this->ensureSkuFree($listing->shop, $changes['seller_sku']);
        }

        $previousPrice = (string) $listing->price_amount;
        $listing->fill([
            'seller_sku' => $changes['seller_sku'],
            'price_amount' => $changes['price_amount'],
            'availability_status' => $changes['availability_status'],
        ])->save();

        if ($previousPrice !== (string) $listing->price_amount) {
            $this->audit->record('listing.price_changed', $listing, ['from' => $previousPrice, 'to' => (string) $listing->price_amount], $listing->shop->mall_id, $listing->shop_id);
        }

        return $listing;
    }

    public function archiveListing(ShopProduct $listing): void
    {
        $listing->update(['status' => 'archived']);
        $this->audit->record('listing.archived', $listing, [], $listing->shop->mall_id, $listing->shop_id);
    }

    public function restoreListing(ShopProduct $listing): void
    {
        $this->ensureOperational($listing->shop);

        $listing->update(['status' => 'active']);
        $this->audit->record('listing.restored', $listing, [], $listing->shop->mall_id, $listing->shop_id);
    }

    /**
     * A shop may edit shared product details only while no other shop lists the product.
     */
    public function canEditSharedProduct(Shop $shop, Product $product): bool
    {
        return ! $product->listings()->where('shop_products.shop_id', '!=', $shop->getKey())->exists();
    }

    /**
     * @param  array{product_category_id: string, name: string, brand?: string|null, description?: string|null}  $details
     */
    public function updateSharedProduct(Shop $shop, Product $product, array $details): Product
    {
        $this->ensureSoleLister($shop, $product);

        $product->update([
            'product_category_id' => $details['product_category_id'],
            'name' => trim($details['name']),
            'brand' => blank($details['brand'] ?? null) ? null : trim($details['brand']),
            'description' => $details['description'] ?? null,
        ]);

        $this->audit->record('product.updated', $product, ['fields' => array_keys($product->getChanges())], $shop->mall_id, $shop->getKey());

        return $product;
    }

    /**
     * Stores an image of a shared product on the public disk, appended after existing images.
     */
    public function addImage(Shop $shop, Product $product, UploadedFile $file, string $altText): ProductImage
    {
        $this->ensureSoleLister($shop, $product);

        $path = $file->store("products/{$product->getKey()}", 'public');

        return $product->images()->create([
            'object_key' => $path,
            'alt_text' => trim($altText),
            'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
        ]);
    }

    /**
     * @param  array{seller_sku: string, price_amount: string|float, availability_status: string}  $listing
     */
    private function storeListing(Shop $shop, ProductVariant $variant, array $listing): ShopProduct
    {
        $this->ensurePrice($listing['price_amount']);

        $created = ShopProduct::query()->create([
            'shop_id' => $shop->getKey(),
            'variant_id' => $variant->getKey(),
            'seller_sku' => $listing['seller_sku'],
            'price_amount' => $listing['price_amount'],
            'currency' => self::CURRENCY,
            'availability_status' => $listing['availability_status'],
            'status' => 'active',
        ]);

        $this->audit->record('listing.created', $created, ['sku' => $created->seller_sku], $shop->mall_id, $shop->getKey());

        return $created;
    }

    private function ensureOperational(Shop $shop): void
    {
        if (! $this->directory->isShopOperational($shop->getKey())) {
            throw new BusinessRuleViolation('listing.shop_not_operational', __('Only an active shop can list products.'));
        }
    }

    private function ensureSkuFree(Shop $shop, string $sku): void
    {
        if (ShopProduct::query()->where('shop_id', $shop->getKey())->where('seller_sku', $sku)->exists()) {
            throw new BusinessRuleViolation('listing.sku_taken', __('Your shop already uses the SKU :sku.', ['sku' => $sku]));
        }
    }

    private function ensurePrice(string|float $price): void
    {
        if (! is_numeric($price) || (float) $price < 0 || (float) $price > 9999999999.99) {
            throw new BusinessRuleViolation('listing.invalid_price', __('Enter a price of zero or more.'));
        }
    }

    private function ensureSoleLister(Shop $shop, Product $product): void
    {
        if (! $this->canEditSharedProduct($shop, $product)) {
            throw new BusinessRuleViolation('product.shared_by_other_shops', __('Other shops also sell this product, so only the mall office can change its shared details.'));
        }
    }
}
