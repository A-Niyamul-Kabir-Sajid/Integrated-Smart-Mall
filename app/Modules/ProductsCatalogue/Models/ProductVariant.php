<?php

namespace App\Modules\ProductsCatalogue\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'product_variants', key: 'variant_id', timestamps: false)]
#[Fillable(['product_id', 'variant_code', 'label', 'attributes_json', 'barcode', 'status'])]
#[UseFactory(ProductVariantFactory::class)]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return HasMany<ShopProduct, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(ShopProduct::class, 'variant_id');
    }

    protected function casts(): array
    {
        return [
            'attributes_json' => 'array',
        ];
    }
}
