<x-layouts.customer :title="__('Maintenance requests').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Maintenance requests to the mall') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Report a problem in or around your unit. The mall office acknowledges it, fixes it and marks it done; you confirm.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.maintenance.store', $shop) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h2 class="font-semibold">{{ __('New request') }}</h2>
            <x-form-field name="title" :label="__('What is wrong?')" required maxlength="150" />
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="category" class="block text-sm font-medium">{{ __('Category') }}</label>
                    <select id="category" name="category" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium">{{ __('Priority') }}</label>
                    <select id="priority" name="priority" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Details') }}</label>
                <textarea id="description" name="description" rows="3" required maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Send to the mall') }}</button>
        </form>

        <nav class="mt-8 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Request status') }}">
            <x-nav-link :href="route('workspace.maintenance.index', $shop)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('workspace.maintenance.index', [$shop, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }}</x-nav-link>
            @endforeach
        </nav>

        @if ($requests->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No requests here yet.') }}</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($requests as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('workspace.maintenance.show', [$shop, $item]) }}" class="font-medium text-brand-700 hover:underline">{{ $item->title }}</a>
                            <span class="text-sm text-slate-600">· {{ $item->request_number }} · {{ $item->category->label() }} · {{ $item->priority->label() }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $item->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
