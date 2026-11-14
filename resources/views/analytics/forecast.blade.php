@php
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp
<x-layouts.customer :title="__('Sales forecast (prototype)').' · '.$title">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ $back }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Back') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Sales forecast (prototype)') }} <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 align-middle text-xs font-medium text-amber-900">{{ __('Estimate') }}</span></h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('A prototype that estimates recorded sales revenue per day for the next :days days from past recorded sales. It is an estimate with stated errors, not a promise, and it is shown with the simple methods it has to beat.', ['days' => $forecast['horizon']]) }}</p>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="data-heading">
            <h2 id="data-heading" class="font-semibold">{{ __('Data and target') }}</h2>
            <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt class="inline text-slate-500">{{ __('Target:') }}</dt> <dd class="inline">{{ $forecast['target'] }}</dd></div>
                <div><dt class="inline text-slate-500">{{ __('History used:') }}</dt> <dd class="inline">{{ $forecast['history']['days'] }} {{ __('days') }} ({{ $forecast['history']['from'] ?? '—' }} {{ __('to') }} {{ $forecast['history']['to'] ?? '—' }}), {{ $forecast['history']['sales_days'] }} {{ __('with a sale') }}</dd></div>
                <div><dt class="inline text-slate-500">{{ __('Recorded revenue in the history:') }}</dt> <dd class="inline">BDT {{ $money($forecast['history']['revenue']) }}</dd></div>
                <div><dt class="inline text-slate-500">{{ __('Simulated records:') }}</dt> <dd class="inline">{{ $forecast['provenance']['simulated'] }} {{ __('of') }} {{ $forecast['provenance']['total'] }} {{ __('recorded sales') }}</dd></div>
                @if ($forecast['status'] === 'ok')
                    <div><dt class="inline text-slate-500">{{ __('Training cutoff:') }}</dt> <dd class="inline">{{ $forecast['training_cutoff'] }}</dd></div>
                    <div><dt class="inline text-slate-500">{{ __('Run id:') }}</dt> <dd class="inline font-mono">{{ $forecast['run_id'] }}</dd> <span class="text-xs text-slate-500">({{ __('the same history always gives the same run id and numbers') }})</span></div>
                @endif
            </dl>
            @if ($forecast['provenance']['simulated'] > 0)
                <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">{{ __('Part of this history is simulated demonstration data, so the forecast demonstrates the method and says nothing about a real shop.') }}</p>
            @endif
        </section>

        @if ($forecast['status'] !== 'ok')
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-700" role="status">{{ $forecast['reason'] }}</p>
        @else
            <section class="mt-6" aria-labelledby="result-heading">
                <h2 id="result-heading" class="font-semibold">{{ __('Forecast for the next :days days', ['days' => $forecast['horizon']]) }}</h2>
                <p class="text-sm text-slate-600">{{ __('Method chosen by cross-validation: :label. Expected total: BDT :total.', ['label' => $forecast['selected_label'], 'total' => $money($forecast['forecast_total'])]) }}</p>
                <x-line-chart :history="$forecast['series']" :forecast="$forecast['forecast']" :caption="__('Recorded sales per day and the forecast')" />
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Forecast per day') }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-4 py-2">{{ __('Day') }}</th><th scope="col" class="px-4 py-2">{{ __('Estimate (BDT)') }}</th><th scope="col" class="px-4 py-2">{{ __('80% interval') }}</th><th scope="col" class="px-4 py-2">{{ __('Repeat last week') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($forecast['forecast'] as $row)
                                <tr><td class="px-4 py-1.5">{{ \Illuminate\Support\Carbon::parse($row['day'])->format('D d M') }}</td><td class="px-4 py-1.5 font-medium">{{ $money($row['point']) }}</td><td class="px-4 py-1.5">{{ $money($row['low']) }} – {{ $money($row['high']) }}</td><td class="px-4 py-1.5">{{ $money($row['baseline']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-8" aria-labelledby="eval-heading">
                <h2 id="eval-heading" class="font-semibold">{{ __('How good is it? Evaluation on days it never saw') }}</h2>
                <p class="mt-1 rounded-lg px-4 py-3 text-sm {{ $forecast['beats_baseline'] ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }}" role="note">{{ $forecast['verdict'] }}</p>
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Forecast evaluation') }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('Method') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Cross-validation MAE') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Hold-out MAE') }}</th>
                                <th scope="col" class="px-4 py-2">RMSE</th>
                                <th scope="col" class="px-4 py-2">sMAPE %</th>
                                <th scope="col" class="px-4 py-2">MASE</th>
                                <th scope="col" class="px-4 py-2">{{ __('Bias') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($forecast['models'] as $model)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $model['label'] }} @if ($model['selected']) <span class="ml-1 rounded bg-brand-100 px-1.5 py-0.5 text-xs text-brand-800">{{ __('chosen') }}</span> @endif</td>
                                    <td class="px-4 py-2">{{ $money($model['cv_mae']) }}</td>
                                    <td class="px-4 py-2">{{ $money($model['holdout']['mae']) }}</td>
                                    <td class="px-4 py-2">{{ $money($model['holdout']['rmse']) }}</td>
                                    <td class="px-4 py-2">{{ $model['holdout']['smape'] }}</td>
                                    <td class="px-4 py-2">{{ $model['holdout']['mase'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $money($model['holdout']['bias']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-600">
                    <li>{{ __('The last :days days were kept aside and never used to fit or choose a method. The cross-validation column uses up to :folds earlier 14-day windows, each predicted only from days before it, and decides the chosen method.', ['days' => $forecast['holdout_days'], 'folds' => $forecast['cv_folds']]) }}</li>
                    <li>{{ __('MAE and RMSE are in BDT per day (lower is better). MASE compares with repeating last week: below 1 is better than that, above 1 is worse. Bias above 0 means the forecast ran high.') }}</li>
                    <li>{{ __('Ridge penalty chosen by cross-validation: :lambda (0 is ordinary least squares).', ['lambda' => $forecast['ridge_lambda']]) }}</li>
                </ul>
            </section>

            @if ($forecast['regression'])
                @php($r = $forecast['regression'])
                <section class="mt-8" aria-labelledby="regression-heading">
                    <h2 id="regression-heading" class="font-semibold">{{ __('What the regression says about the history') }}</h2>
                    <p class="text-sm text-slate-600">{{ __('Ordinary least squares on the whole history: revenue = intercept + trend per week + weekday effects (compared with Monday). :n days.', ['n' => $r['observations']]) }}</p>
                    <p class="mt-1 text-sm">{{ __('Trend: BDT :trend per week (p = :p). R² :r2, adjusted R² :adj, Durbin–Watson :dw.', ['trend' => $money($r['trend_per_week']), 'p' => $r['trend_p'] ?? '—', 'r2' => $r['r_squared'], 'adj' => $r['adjusted_r_squared'] ?? '—', 'dw' => $r['durbin_watson'] ?? '—']) }}</p>
                    <div class="mt-2 grid gap-x-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($r['weekday_effects'] as $e) <p>{{ $e['weekday'] }}: {{ ($e['effect'] > 0 ? '+' : '').$money($e['effect']) }} <span class="text-slate-500">(p = {{ $e['p'] ?? '—' }})</span></p> @endforeach
                    </div>
                </section>
            @endif
        @endif

        <section class="mt-8" aria-labelledby="limits-heading">
            <h2 id="limits-heading" class="font-semibold">{{ __('Limits you should know') }}</h2>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-700">
                @foreach ($forecast['limitations'] as $note) <li>{{ $note }}</li> @endforeach
            </ul>
            <p class="mt-3 text-sm"><a href="{{ $back }}" class="font-medium text-brand-700 hover:underline">{{ __('Back to the dashboard') }}</a></p>
        </section>
    </div>
</x-layouts.customer>
