<x-layouts.customer :title="__('Facilities').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Facilities') }}</h1>
            <a href="{{ route('facilities.admin.create', $mall) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add facility') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('Open and temporarily closed facilities appear in the customer facility finder; archived ones are hidden.') }}</p>

        @if ($facilities->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No facilities yet.') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Floor') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Type') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Step-free') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($facilities as $facility)
                            <tr @class(['opacity-60' => $facility->status->value === 'archived'])>
                                <td class="px-4 py-2">{{ __($facility->floor->name) }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium">{{ $facility->name }}</span>
                                    @if ($facility->location_note)
                                        <span class="block text-xs text-slate-500">{{ $facility->location_note }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ __($facility->facility_type->label()) }}</td>
                                <td class="px-4 py-2">{{ $facility->is_accessible ? __('Yes') : __('No') }}</td>
                                <td class="px-4 py-2">{{ ['open' => __('Open'), 'temporarily_closed' => __('Temporarily closed'), 'archived' => __('Archived')][$facility->status->value] }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('facilities.admin.edit', [$mall, $facility]) }}" class="font-medium text-brand-700 hover:underline" aria-label="{{ __('Edit :name on :floor', ['name' => $facility->name, 'floor' => $facility->floor->name]) }}">{{ __('Edit') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.customer>
