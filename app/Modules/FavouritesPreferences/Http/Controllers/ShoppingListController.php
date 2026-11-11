<?php

namespace App\Modules\FavouritesPreferences\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FavouritesPreferences\Http\Requests\StoreListItemRequest;
use App\Modules\FavouritesPreferences\Http\Requests\StoreShoppingListRequest;
use App\Modules\FavouritesPreferences\Http\Requests\UpdateListItemRequest;
use App\Modules\FavouritesPreferences\Models\ShoppingList;
use App\Modules\FavouritesPreferences\Models\ShoppingListItem;
use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in customer's shopping lists. A list or line of another account
 * answers 404, exactly like one that does not exist.
 */
class ShoppingListController extends Controller
{
    public function __construct(private ShoppingListService $lists) {}

    public function index(Request $request): View
    {
        return view('account.lists.index', [
            'lists' => $this->lists->listsFor($request->user()->getKey()),
            'malls' => Mall::query()->active()->orderBy('name')->get(['mall_id', 'name']),
        ]);
    }

    public function store(StoreShoppingListRequest $request): RedirectResponse
    {
        $list = $this->lists->create(
            $request->user()->getKey(),
            Mall::query()->findOrFail($request->validated('mall_id')),
            $request->validated('name'),
        );

        return redirect()->route('account.lists.show', $list)->with('status', __('Your list ":name" is ready.', ['name' => $list->name]));
    }

    public function show(Request $request, string $list): View
    {
        $shoppingList = $this->owned($request, $list);

        return view('account.lists.show', [
            'list' => $shoppingList,
            'items' => $this->lists->itemsOf($shoppingList),
        ]);
    }

    public function update(StoreShoppingListRequest $request, string $list): RedirectResponse
    {
        $shoppingList = $this->lists->rename($this->owned($request, $list), $request->validated('name'));

        return redirect()->route('account.lists.show', $shoppingList)->with('status', __('The list was renamed.'));
    }

    public function destroy(Request $request, string $list): RedirectResponse
    {
        $this->lists->delete($this->owned($request, $list));

        return redirect()->route('account.lists.index')->with('status', __('The list was deleted.'));
    }

    public function storeItem(StoreListItemRequest $request, string $list): RedirectResponse
    {
        $shoppingList = $this->owned($request, $list);
        $quantity = (int) ($request->validated('quantity') ?? 1);
        $note = $request->validated('note');

        if ($request->filled('product_id')) {
            $product = Product::query()->findOrFail($request->validated('product_id'));
            $this->lists->addProduct($shoppingList, $product, $quantity, $note);

            return back()->with('status', __(':item was added to ":list".', ['item' => $product->name, 'list' => $shoppingList->name]));
        }

        $item = $this->lists->addCustomItem($shoppingList, $request->validated('label'), $quantity, $note);

        return back()->with('status', __(':item was added to ":list".', ['item' => $item->label, 'list' => $shoppingList->name]));
    }

    public function updateItem(UpdateListItemRequest $request, string $list, string $item): RedirectResponse
    {
        $this->lists->updateItem($this->ownedItem($request, $list, $item), $request->validated());

        return redirect()->route('account.lists.show', $list);
    }

    public function destroyItem(Request $request, string $list, string $item): RedirectResponse
    {
        $this->lists->removeItem($this->ownedItem($request, $list, $item));

        return redirect()->route('account.lists.show', $list)->with('status', __('The item was removed.'));
    }

    private function owned(Request $request, string $listId): ShoppingList
    {
        return $this->lists->findOwned($request->user()->getKey(), $listId) ?? abort(404);
    }

    private function ownedItem(Request $request, string $listId, string $itemId): ShoppingListItem
    {
        return $this->owned($request, $listId)->items()->findOrFail($itemId);
    }
}
