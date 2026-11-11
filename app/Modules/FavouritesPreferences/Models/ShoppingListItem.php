<?php

namespace App\Modules\FavouritesPreferences\Models;

use App\Modules\ProductsCatalogue\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a shopping list: a product reference or free text, with a label
 * snapshot, a quantity, an optional note and a done flag (decision D-012).
 */
#[Table(name: 'shopping_list_items', key: 'shopping_list_item_id')]
#[Fillable(['shopping_list_id', 'product_id', 'label', 'quantity', 'note', 'is_done'])]
class ShoppingListItem extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_done' => false];

    /**
     * @return BelongsTo<ShoppingList, $this>
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'shopping_list_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_done' => 'boolean',
        ];
    }
}
