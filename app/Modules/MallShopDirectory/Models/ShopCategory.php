<?php

namespace App\Modules\MallShopDirectory\Models;

use Database\Factories\ShopCategoryFactory;
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

/**
 * SHOP_CATEGORY: shop classification such as food, clothing or electronics,
 * optionally nested under a parent (the chain must stay acyclic).
 */
#[Table(name: 'shop_categories', key: 'shop_category_id', timestamps: false)]
#[Fillable(['parent_category_id', 'slug', 'name'])]
#[UseFactory(ShopCategoryFactory::class)]
class ShopCategory extends Model
{
    /** @use HasFactory<ShopCategoryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<ShopCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    /**
     * @return HasMany<ShopCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_category_id')->orderBy('name');
    }

    /**
     * @return HasMany<Shop, $this>
     */
    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'shop_category_id');
    }

    #[Scope]
    protected function topLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_category_id');
    }
}
