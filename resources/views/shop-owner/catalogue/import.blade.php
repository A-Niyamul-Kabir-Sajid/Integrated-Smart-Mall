<x-layouts.customer :title="__('Import CSV').' · '.$shop->name">
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8">
        <a href="{{ route('workspace.catalogue.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Catalogue') }}</a>
        <h1 class="text-2xl font-semibold">{{ __('Import products from a CSV file') }}</h1>
        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.catalogue.import.preview', $shop) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <p class="text-sm text-slate-600">{{ __('The first line must be exactly:') }} <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ implode(',', $columns) }}</code></p>
            <p class="text-xs text-slate-500">{{ __('Category is a product category slug (e.g. drinks); availability is available, unavailable or unknown; prices are in BDT. Nothing is saved until you confirm the preview, and a file with any error saves nothing.') }}</p>
            <input name="file" type="file" accept=".csv,text/csv" required class="text-sm">
            @error('file') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
            <div><button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Check file') }}</button></div>
        </form>

        @isset($rows)
            @if ($rowErrors !== [])
                <section class="rounded-xl border border-rose-200 bg-rose-50 p-6" aria-labelledby="errors-heading" role="alert">
                    <h2 id="errors-heading" class="font-semibold text-rose-900">{{ trans_choice(':count row needs fixing. Nothing was imported.|:count rows need fixing. Nothing was imported.', count($rowErrors), ['count' => count($rowErrors)]) }}</h2>
                    <ul class="mt-3 space-y-1 text-sm text-rose-900">
                        @foreach ($rowErrors as $line => $messages)
                            <li><span class="font-medium">{{ __('Row :line', ['line' => $line]) }}:</span> {{ implode(' ', $messages) }}</li>
                        @endforeach
                    </ul>
                </section>
            @else
                <section class="rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="preview-heading">
                    <h2 id="preview-heading" class="font-semibold">{{ trans_choice(':count product is ready to import.|:count products are ready to import.', count($rows), ['count' => count($rows)]) }}</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr><th class="px-3 py-2">{{ __('Row') }}</th><th class="px-3 py-2">{{ __('Product') }}</th><th class="px-3 py-2">{{ __('SKU') }}</th><th class="px-3 py-2 text-right">{{ __('Price') }}</th><th class="px-3 py-2">{{ __('Action') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rows as $row)
                                    <tr><td class="px-3 py-2">{{ $row['line'] }}</td><td class="px-3 py-2">{{ $row['name'] }} · {{ $row['option'] }}</td><td class="px-3 py-2 font-mono text-xs">{{ $row['sku'] }}</td><td class="px-3 py-2 text-right tabular-nums">৳{{ $row['price'] }}</td><td class="px-3 py-2">{{ __($row['action']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="{{ route('workspace.catalogue.import.store', $shop) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Import these products') }}</button>
                    </form>
                </section>
            @endif
        @endisset
    </div>
</x-layouts.customer>
