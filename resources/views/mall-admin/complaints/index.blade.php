<x-layouts.customer :title="__('Complaints')" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Complaints') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Customers file complaints about the mall or one of its shops. Assign each one to an administrator, work it and resolve or reject it with a note; the customer sees your notes.') }}</p>

        <form method="GET" action="{{ route('mall-admin.complaints.index', $mall) }}" class="mt-6 flex flex-wrap items-end gap-3">
            <div>
                <label for="status" class="block text-xs font-medium">{{ __('Status') }}</label>
                <select id="status" name="status" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected(($filters['status'] ?? null) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine'])) class="size-4 accent-brand-600"> {{ __('Assigned to me') }}</label>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Filter') }}</button>
        </form>

        @if ($complaints->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No complaints here.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($complaints as $complaint)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('mall-admin.complaints.show', [$mall, $complaint]) }}" class="font-medium text-brand-700 hover:underline">{{ $complaint->subject }}</a>
                            <span class="block text-sm text-slate-600">{{ $complaint->complaint_number }} · {{ $complaint->category->label() }} · {{ $complaint->shop?->name ?? __('The mall') }} · {{ $complaint->customer->display_name }} · {{ $complaint->created_at->timezone($mall->timezone)->format('d M Y') }}@if ($complaint->assignee) · {{ __('with :name', ['name' => $complaint->assignee->display_name]) }}@endif</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $complaint->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $complaints->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
