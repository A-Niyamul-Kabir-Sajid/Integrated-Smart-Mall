<?php

namespace App\Modules\FavouritesPreferences\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FAVOURITE_SHOP: a shop saved by an account. The key is (user_id, shop_id),
 * so rows are written and removed by FavouriteService, never by primary key.
 */
#[Table(name: 'favourite_shops', key: 'shop_id', keyType: 'string', incrementing: false)]
#[Fillable(['user_id', 'shop_id'])]
class FavouriteShop extends Model
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
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
