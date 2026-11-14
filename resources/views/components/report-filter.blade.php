@props(['action', 'period', 'export' => null])

<form method="GET" action="{{ $action }}" class="mt-6 flex flex-wrap items-end gap-3" aria-label="{{ __('Report filters') }}">
    <div>
        <label for="from" class="block text-xs font-medium">{{ __('From') }}</label>
        <input id="from" name="from" type="date" value="{{ $period->from->format('Y-m-d') }}" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium">{{ __('To (included)') }}</label>
        <input id="to" name="to" type="date" value="{{ $period->to->format('Y-m-d') }}" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
    </div>
    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Show') }}</button>
    @if ($export)
        <a href="{{ $export }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Download CSV') }}</a>
    @endif
</form>

@error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

<p class="mt-4 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700" role="note">
    {{ __('Period :from to :to (mall time, :zone).', ['from' => $period->from->format('d M Y'), 'to' => $period->to->format('d M Y'), 'zone' => $period->timezone]) }}
    {{ __('Figures come from recorded sales, orders, reviews and complaints in the database and are recalculated each time you open this page.') }}
</p>
