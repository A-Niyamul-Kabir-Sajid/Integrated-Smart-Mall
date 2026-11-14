<x-layouts.customer :title="__('Monthly reports').' · '.$title">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ $back }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Back') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Monthly reports') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('A monthly report keeps the figures of one calendar month as they were when you generated it, compares them with the month before, and adds a short summary written only from those figures. Generating the same month again replaces it. A month that is not over is reported up to today and marked as such.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
        @error('month') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $storeRoute }}" class="mt-6 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="month" class="block text-xs font-medium">{{ __('Month') }}</label>
                <select id="month" name="month" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                    @foreach ($months as $month)
                        <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::parse($month.'-01')->format('F Y') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Generate report') }}</button>
        </form>

        <h2 class="mt-8 font-semibold">{{ __('Generated reports') }}</h2>
        @if ($reports->isEmpty())
            <p class="mt-2 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No report has been generated yet.') }}</p>
        @else
            <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($reports as $report)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <a href="{{ $showRoute($report) }}" class="font-medium text-brand-700 hover:underline">{{ \Illuminate\Support\Carbon::parse($report->month)->format('F Y') }}</a>
                        <span class="text-slate-500">{{ __('generated :at', ['at' => $report->source_cutoff_at->format('d M Y H:i')]) }}@if ($report->partial) · {{ __('month not over') }}@endif</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
