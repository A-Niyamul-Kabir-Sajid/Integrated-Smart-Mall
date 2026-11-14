<x-layouts.customer :title="__('File a complaint')" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('account.complaints.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('My complaints') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('File a complaint') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Tell the mall office what went wrong. An administrator will be assigned and you will see every step here.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('account.complaints.store') }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <input type="hidden" name="mall_id" value="{{ $mall->getKey() }}">
            <p class="text-sm">{{ __('Mall') }}: <span class="font-medium">{{ $mall->name }}</span>
                @if ($malls->count() > 1)
                    · @foreach ($malls->where('mall_id', '!=', $mall->getKey()) as $other)<a href="{{ route('account.complaints.create', ['mall' => $other->slug]) }}" class="text-brand-700 hover:underline">{{ $other->name }}</a> @endforeach
                @endif
            </p>
            <div>
                <label for="shop_id" class="block text-sm font-medium">{{ __('About which shop? (optional)') }}</label>
                <select id="shop_id" name="shop_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                    <option value="">{{ __('The mall itself') }}</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->shop_id }}" @selected(old('shop_id', $shopId) === $shop->shop_id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                @error('shop_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="category" class="block text-sm font-medium">{{ __('What is it about?') }}</label>
                <select id="category" name="category" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
                @error('category') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <x-form-field name="subject" :label="__('Subject')" required maxlength="150" />
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('What happened?') }}</label>
                <textarea id="description" name="description" rows="5" required minlength="10" maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Send complaint') }}</button>
        </form>
    </div>
</x-layouts.customer>
