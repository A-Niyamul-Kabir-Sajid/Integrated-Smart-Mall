<?php

namespace App\Modules\ProductsCatalogue\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table(name: 'products', key: 'product_id')]
#[Fillable(['product_category_id', 'name', 'brand', 'description', 'status'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * Every shop listing of any of this product's variants.
     *
     * @return HasManyThrough<ShopProduct, ProductVariant, $this>
     */
    public function listings(): HasManyThrough
    {
        return $this->hasManyThrough(ShopProduct::class, ProductVariant::class, 'product_id', 'variant_id', 'product_id', 'variant_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
