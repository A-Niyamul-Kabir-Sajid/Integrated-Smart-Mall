<x-layouts.customer :title="__('Shopping lists')">
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-10">
        <div>
            <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
            <h1 class="mt-2 text-2xl font-semibold">{{ __('Shopping lists') }}</h1>
            <p class="text-slate-600">{{ __('Plan a visit: one list per mall, with products or anything you type.') }}</p>
        </div>

        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($lists->isEmpty())
            <p class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">{{ __('You have no shopping lists yet.') }}</p>
        @else
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($lists as $list)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <a href="{{ route('account.lists.show', $list) }}" class="font-medium text-brand-700 hover:underline">{{ $list->name }}</a>
                            <p class="text-xs text-slate-500">{{ $list->mall->name }} · {{ trans_choice(':count item|:count items', $list->items_count, ['count' => $list->items_count]) }}@if ($list->done_items_count > 0) · {{ __(':count done', ['count' => $list->done_items_count]) }}@endif</p>
                        </div>
                        <span class="text-xs text-slate-500">{{ __('Changed :date', ['date' => $list->updated_at->timezone('Asia/Dhaka')->format('d M, H:i')]) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('account.lists.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h2 class="font-semibold">{{ __('New list') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="name" :label="__('List name')" required maxlength="80" :placeholder="__('e.g. Weekly groceries')" />
                <div>
                    <label for="mall_id" class="block text-sm font-medium">{{ __('Mall') }}</label>
                    <select id="mall_id" name="mall_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($malls as $mall)
                            <option value="{{ $mall->mall_id }}" @selected(old('mall_id') === $mall->mall_id)>{{ $mall->name }}</option>
                        @endforeach
                    </select>
                    @error('mall_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Create list') }}</button>
        </form>
    </div>
</x-layouts.customer>
