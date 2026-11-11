<?php

namespace App\Modules\FavouritesPreferences\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\FavouritesPreferences\Models\ShoppingList;
use App\Modules\FavouritesPreferences\Models\ShoppingListItem;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shopping lists of one account (decision D-012). Every read and write is
 * scoped to the owner: another account's list is simply not found.
 */
class ShoppingListService
{
    public const MAX_LISTS = 20;

    public const MAX_ITEMS = 100;

    public const MAX_QUANTITY = 99;

    public function __construct(private CatalogueLookup $catalogue) {}

    /**
     * The account's lists, most recently changed first, with item counts and malls.
     *
     * @return Collection<int, ShoppingList>
     */
    public function listsFor(string $userId, ?string $mallId = null): Collection
    {
        return ShoppingList::query()
            ->where('user_id', $userId)
            ->when($mallId !== null, fn ($query) => $query->where('mall_id', $mallId))
            ->with('mall')
            ->withCount(['items', 'items as done_items_count' => fn ($query) => $query->where('is_done', true)])
            ->latest('updated_at')
            ->get();
    }

    /**
     * The list when it belongs to the account, or null.
     */
    public function findOwned(string $userId, string $listId): ?ShoppingList
    {
        return ShoppingList::query()->where('user_id', $userId)->with('mall')->find($listId);
    }

    public function create(string $userId, Mall $mall, string $name): ShoppingList
    {
        if ($mall->status !== MallStatus::Active) {
            throw new BusinessRuleViolation('shopping_list.mall_not_active', __('Choose a mall that is open to customers.'));
        }

        return DB::transaction(function () use ($userId, $mall, $name): ShoppingList {
            $count = ShoppingList::query()->where('user_id', $userId)->lockForUpdate()->count();

            if ($count >= self::MAX_LISTS) {
                throw new BusinessRuleViolation('shopping_list.limit_reached', __('You can keep at most :max shopping lists. Delete one first.', ['max' => self::MAX_LISTS]));
            }

            return ShoppingList::query()->create(['user_id' => $userId, 'mall_id' => $mall->getKey(), 'name' => trim($name)]);
        });
    }

    public function rename(ShoppingList $list, string $name): ShoppingList
    {
        $list->update(['name' => trim($name)]);

        return $list;
    }

    public function delete(ShoppingList $list): void
    {
        $list->delete();
    }

    /**
     * Adds a product the list's mall sells. Adding a product that is already on the
     * list (and not done) raises its quantity instead of creating a second line.
     */
    public function addProduct(ShoppingList $list, Product $product, int $quantity = 1, ?string $note = null): ShoppingListItem
    {
        if ($this->catalogue->soldProductIds($list->mall, [$product->getKey()]) === []) {
            throw new BusinessRuleViolation('shopping_list.product_not_sold_here', __('No shop in :mall sells this product right now.', ['mall' => $list->mall->name]));
        }

        $existing = $list->items()->where('product_id', $product->getKey())->where('is_done', false)->first();

        if ($existing !== null) {
            $existing->update(['quantity' => min(self::MAX_QUANTITY, $existing->quantity + $quantity), 'note' => $note ?? $existing->note]);
            $list->touch();

            return $existing;
        }

        return $this->addLine($list, ['product_id' => $product->getKey(), 'label' => $product->name, 'quantity' => $quantity, 'note' => $note]);
    }

    /**
     * Adds a free-text line such as "birthday candles".
     */
    public function addCustomItem(ShoppingList $list, string $label, int $quantity = 1, ?string $note = null): ShoppingListItem
    {
        return $this->addLine($list, ['product_id' => null, 'label' => trim($label), 'quantity' => $quantity, 'note' => $note]);
    }

    /**
     * @param  array{quantity?: int, note?: string|null, is_done?: bool}  $changes
     */
    public function updateItem(ShoppingListItem $item, array $changes): ShoppingListItem
    {
        $item->update(array_intersect_key($changes, array_flip(['quantity', 'note', 'is_done'])));
        $item->shoppingList->touch();

        return $item;
    }

    public function removeItem(ShoppingListItem $item): void
    {
        $item->delete();
        $item->shoppingList->touch();
    }

    /**
     * The list's items, open ones first, each with `is_sold_here`: false for free-text
     * lines and for products the list's mall no longer sells.
     *
     * @return Collection<int, ShoppingListItem>
     */
    public function itemsOf(ShoppingList $list): Collection
    {
        $items = $list->items()->orderBy('is_done')->orderBy('created_at')->orderBy('shopping_list_item_id')->get();
        $sold = $this->catalogue->soldProductIds($list->mall, $items->pluck('product_id')->filter()->unique()->values()->all());

        return $items->each(fn (ShoppingListItem $item) => $item->setAttribute('is_sold_here', $item->product_id !== null && in_array($item->product_id, $sold, true)));
    }

    /**
     * @param  array{product_id: string|null, label: string, quantity: int, note: string|null}  $line
     */
    private function addLine(ShoppingList $list, array $line): ShoppingListItem
    {
        return DB::transaction(function () use ($list, $line): ShoppingListItem {
            ShoppingList::query()->whereKey($list->getKey())->lockForUpdate()->first();

            if ($list->items()->count() >= self::MAX_ITEMS) {
                throw new BusinessRuleViolation('shopping_list.item_limit_reached', __('A list can hold at most :max items.', ['max' => self::MAX_ITEMS]));
            }

            $item = $list->items()->create($line);
            $list->touch();

            return $item;
        });
    }
}
