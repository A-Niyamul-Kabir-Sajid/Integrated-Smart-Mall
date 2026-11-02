<?php

namespace App\Modules\ProductsCatalogue\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'product_categories', key: 'product_category_id', timestamps: false)]
#[Fillable(['parent_category_id', 'slug', 'name'])]
#[UseFactory(ProductCategoryFactory::class)]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}
