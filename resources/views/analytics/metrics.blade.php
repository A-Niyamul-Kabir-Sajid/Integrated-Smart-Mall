<x-layouts.customer :title="__('How each figure is calculated').' · '.$title">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ $back }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Back') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('How each figure is calculated') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Every figure on the dashboards, monthly reports and downloads of :audience is defined here: what it means, where it comes from, the formula, how it is split up, how often it is refreshed and who may see it. A definition never changes silently; a changed formula gets a new code.', ['audience' => $audience]) }}</p>

        <div class="mt-6 space-y-4">
            @foreach ($rows as $row)
                <article class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="m-{{ $row['code'] }}">
                    <h2 id="m-{{ $row['code'] }}" class="font-semibold">{{ $row['label'] }} <span class="ml-2 font-mono text-xs font-normal text-slate-500">{{ $row['code'] }} · v{{ $row['version'] }}</span></h2>
                    @unless ($row['available'])
                        <p class="mt-1 rounded bg-amber-50 px-3 py-2 text-sm text-amber-900" role="note">{{ $row['unavailable_reason'] }}</p>
                    @endunless
                    <dl class="mt-2 space-y-1 text-sm">
                        <div><dt class="inline font-medium">{{ __('Formula:') }}</dt> <dd class="inline">{{ $row['formula'] }}</dd></div>
                        @if ($row['algorithm']) <div><dt class="inline font-medium">{{ __('Method:') }}</dt> <dd class="inline">{{ $row['algorithm'] }}</dd></div> @endif
                        <div><dt class="inline font-medium">{{ __('Unit:') }}</dt> <dd class="inline">{{ $row['unit'] }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('Source:') }}</dt> <dd class="inline">{{ $row['sources'] === [] ? __('none yet') : implode(', ', $row['sources']) }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('Split by:') }}</dt> <dd class="inline">{{ $row['dimensions'] }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('Refresh:') }}</dt> <dd class="inline">{{ $row['refresh'] }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('Who may see it:') }}</dt> <dd class="inline">{{ $row['visibility'] }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-sm text-slate-600">{{ __('Days are calendar days in the mall\'s time zone. Money is in BDT. Real visitor activity and demonstration data are kept apart.') }}</p>
    </div>
</x-layouts.customer>
