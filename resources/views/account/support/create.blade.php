<x-layouts.customer :title="__('Contact :shop', ['shop' => $shop->name])" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Contact :shop', ['shop' => $shop->name]) }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('support.store', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <fieldset class="space-y-1">
                <legend class="text-sm font-medium">{{ __('What is it about?') }}</legend>
                @foreach ($kinds as $kind)
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="kind" value="{{ $kind->value }}" @checked(old('kind', 'question') === $kind->value) class="accent-brand-600"> {{ $kind->label() }}</label>
                @endforeach
                @error('kind') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
            </fieldset>
            <x-form-field name="subject" :label="__('Subject')" required maxlength="150" />
            <div>
                <label for="body" class="block text-sm font-medium">{{ __('Message') }}</label>
                <textarea id="body" name="body" rows="5" required maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('body') }}</textarea>
                @error('body') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Send message') }}</button>
        </form>
    </div>
</x-layouts.customer>
