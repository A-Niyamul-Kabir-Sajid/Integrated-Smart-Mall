<?php

namespace App\Modules\ProductsCatalogue\Models;

use App\Modules\MallShopDirectory\Models\Shop;
use Database\Factories\ShopProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop-specific listing and price for one product variant.
 */
#[Table(name: 'shop_products', key: 'shop_product_id')]
#[Fillable(['shop_id', 'variant_id', 'seller_sku', 'price_amount', 'currency', 'availability_status', 'status'])]
#[UseFactory(ShopProductFactory::class)]
class ShopProduct extends Model
{
    /** @use HasFactory<ShopProductFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = null;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
        ];
    }
}
