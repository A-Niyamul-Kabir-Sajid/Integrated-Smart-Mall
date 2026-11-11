<x-layouts.customer :title="$list->name" :mall="$list->mall">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10">
        <div>
            <a href="{{ route('account.lists.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Shopping lists') }}</a>
            <h1 class="mt-2 text-2xl font-semibold">{{ $list->name }}</h1>
            <p class="text-slate-600">{{ $list->mall->name }} · {{ trans_choice(':count item|:count items', $items->count(), ['count' => $items->count()]) }}</p>
        </div>

        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($items->isEmpty())
            <p class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">
                {{ __('This list is empty. Add something below, or use "Add to list" on a product page.') }}
                <a href="{{ route('products.index', ['publicMall' => $list->mall->slug]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Find products') }}</a>
            </p>
        @else
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($items as $item)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                        <form method="POST" action="{{ route('account.lists.items.update', [$list, $item]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_done" value="{{ $item->is_done ? '0' : '1' }}">
                            <button type="submit" aria-pressed="{{ $item->is_done ? 'true' : 'false' }}" aria-label="{{ $item->is_done ? __('Mark :item as not bought', ['item' => $item->label]) : __('Mark :item as bought', ['item' => $item->label]) }}"
                                @class(['grid size-6 place-items-center rounded border text-sm', 'border-brand-600 bg-brand-600 text-white' => $item->is_done, 'border-slate-400 bg-white' => ! $item->is_done])>
                                @if ($item->is_done) ✓ @endif
                            </button>
                        </form>
                        <div class="min-w-40 flex-1">
                            @if ($item->is_sold_here)
                                <a href="{{ route('products.show', ['publicMall' => $list->mall->slug, 'publicProduct' => $item->product_id]) }}" @class(['font-medium text-brand-700 hover:underline', 'line-through opacity-60' => $item->is_done])>{{ $item->label }}</a>
                            @else
                                <span @class(['font-medium', 'line-through opacity-60' => $item->is_done])>{{ $item->label }}</span>
                                @if ($item->product_id !== null)
                                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ __('Not sold here any more') }}</span>
                                @endif
                            @endif
                            @if ($item->note)
                                <p class="text-xs text-slate-500">{{ $item->note }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('account.lists.items.update', [$list, $item]) }}" class="flex items-center gap-1">
                            @csrf
                            @method('PATCH')
                            <label for="quantity-{{ $item->shopping_list_item_id }}" class="sr-only">{{ __('Quantity of :item', ['item' => $item->label]) }}</label>
                            <input id="quantity-{{ $item->shopping_list_item_id }}" name="quantity" type="number" min="1" max="99" value="{{ $item->quantity }}" class="w-16 rounded-lg px-2 py-1 text-right ring-1 ring-slate-300">
                            <button type="submit" class="text-xs font-medium text-brand-700 hover:underline">{{ __('Update') }}</button>
                        </form>
                        <form method="POST" action="{{ route('account.lists.items.destroy', [$list, $item]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-rose-700 hover:underline" aria-label="{{ __('Remove :name', ['name' => $item->label]) }}">{{ __('Remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('account.lists.items.store', $list) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h2 class="font-semibold">{{ __('Add an item') }}</h2>
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2"><x-form-field name="label" :label="__('What to buy')" maxlength="160" required :placeholder="__('e.g. birthday candles')" /></div>
                <x-form-field name="quantity" type="number" :label="__('Quantity')" value="1" min="1" max="99" />
                <x-form-field name="note" :label="__('Note (optional)')" maxlength="200" />
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Add') }}</button>
        </form>

        <div class="grid gap-6 sm:grid-cols-2">
            <form method="POST" action="{{ route('account.lists.update', $list) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                @csrf
                @method('PUT')
                <x-form-field name="name" :label="__('Rename list')" :value="$list->name" required maxlength="80" />
                <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Save name') }}</button>
            </form>
            <form method="POST" action="{{ route('account.lists.destroy', $list) }}" class="space-y-3 rounded-xl border border-rose-200 bg-white p-6" onsubmit="return confirm(@js(__('Delete this list and all its items?')))">
                @csrf
                @method('DELETE')
                <h2 class="font-semibold">{{ __('Delete list') }}</h2>
                <p class="text-sm text-slate-600">{{ __('The list and all its items are removed. This cannot be undone.') }}</p>
                <button type="submit" class="rounded-lg border border-rose-600 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('Delete list') }}</button>
            </form>
        </div>
    </div>
</x-layouts.customer>
