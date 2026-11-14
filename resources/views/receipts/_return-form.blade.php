{{-- Expects $purchase (with items) and $returnForm: blocked (string|null), committed (map), kinds, action. --}}
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 print:hidden" aria-labelledby="return-heading">
    <h2 id="return-heading" class="font-semibold">{{ __('Return or exchange') }}</h2>

    @error('business') <p class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

    @if ($returnForm['blocked'])
        <p class="mt-2 text-sm text-slate-600">{{ $returnForm['blocked'] }}</p>
    @else
        <form method="POST" action="{{ $returnForm['action'] }}" class="mt-3 space-y-4">
            @csrf
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach ($purchase->items as $item)
                    @php($remaining = $item->quantity - $returnForm['committed']->get($item->getKey(), 0))
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 text-sm">
                        <label for="return-qty-{{ $item->getKey() }}" class="flex-1">{{ $item->product_label_snapshot }} <span class="text-slate-500">({{ __(':count left to return', ['count' => $remaining]) }})</span></label>
                        <input id="return-qty-{{ $item->getKey() }}" type="number" name="quantities[{{ $item->getKey() }}]" min="0" max="{{ $remaining }}" value="{{ old('quantities.'.$item->getKey(), 0) }}" @disabled($remaining < 1) class="w-20 rounded-lg px-2 py-1.5 text-right ring-1 ring-slate-300 disabled:bg-slate-50">
                    </li>
                @endforeach
            </ul>
            <fieldset class="space-y-1">
                <legend class="text-sm font-medium">{{ __('What do you want?') }}</legend>
                @foreach ($returnForm['kinds'] as $kind)
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="kind" value="{{ $kind->value }}" @checked(old('kind', 'return') === $kind->value) class="accent-brand-600"> {{ $kind->label() }}</label>
                @endforeach
            </fieldset>
            <div>
                <label for="return-reason" class="block text-sm font-medium">{{ __('Why?') }}</label>
                <textarea id="return-reason" name="reason" rows="2" required minlength="5" maxlength="500" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('reason') }}</textarea>
                @error('reason') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Send request') }}</button>
        </form>
    @endif
</section>
