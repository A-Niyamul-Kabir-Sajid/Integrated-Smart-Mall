<?php

namespace App\Modules\FavouritesPreferences\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use Database\Factories\ShoppingListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named shopping list of one account for one mall (decision D-012).
 */
#[Table(name: 'shopping_lists', key: 'shopping_list_id')]
#[Fillable(['user_id', 'mall_id', 'name'])]
#[UseFactory(ShoppingListFactory::class)]
class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return HasMany<ShoppingListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class, 'shopping_list_id');
    }
}
