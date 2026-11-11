<?php

namespace App\Modules\FavouritesPreferences\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FAVOURITE_PRODUCT: a shop listing saved by an account, keeping the seller
 * context. The key is (user_id, shop_product_id); see FavouriteService.
 */
#[Table(name: 'favourite_products', key: 'shop_product_id', keyType: 'string', incrementing: false)]
#[Fillable(['user_id', 'shop_product_id'])]
class FavouriteProduct extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }
}
