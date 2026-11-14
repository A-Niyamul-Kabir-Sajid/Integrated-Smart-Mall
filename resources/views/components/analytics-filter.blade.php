@props(['action', 'period', 'label', 'kind' => null, 'withKind' => false])

<form method="GET" action="{{ $action }}" class="mt-6 flex flex-wrap items-end gap-3" aria-label="{{ __('Report filters') }}">
    <div>
        <label for="from" class="block text-xs font-medium">{{ __('From') }}</label>
        <input id="from" name="from" type="date" value="{{ $period->from->format('Y-m-d') }}" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium">{{ __('To (included)') }}</label>
        <input id="to" name="to" type="date" value="{{ $period->to->format('Y-m-d') }}" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
    </div>
    @if ($withKind)
        <div>
            <label for="kind" class="block text-xs font-medium">{{ __('Search') }}</label>
            <select id="kind" name="kind" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                <option value="">{{ __('Shops and products') }}</option>
                <option value="shop" @selected($kind === 'shop')>{{ __('Shop search') }}</option>
                <option value="product" @selected($kind === 'product')>{{ __('Product search') }}</option>
            </select>
        </div>
    @endif
    <div>
        <label for="dataset" class="block text-xs font-medium">{{ __('Data') }}</label>
        <select id="dataset" name="dataset" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
            @foreach (\App\Modules\AnalyticsReports\Enums\DatasetLabel::cases() as $option)
                <option value="{{ $option->value }}" @selected($label === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Show') }}</button>
</form>

@error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

<p @class(['mt-4 rounded-lg px-4 py-3 text-sm', 'bg-slate-100 text-slate-700' => $label->value === 'real', 'bg-amber-50 text-amber-900' => $label->value === 'demo']) role="note">
    <span class="font-medium">{{ $label->label() }}.</span>
    {{ __('Period :from to :to (mall time, :zone).', ['from' => $period->from->format('d M Y'), 'to' => $period->to->format('d M Y'), 'zone' => $period->timezone]) }}
    @if ($label->value === 'demo') {{ __('These numbers were generated for demonstration and say nothing about real visitors.') }} @endif
</p>
