<x-layouts.customer :title="__('My complaints')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('My account') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('My complaints') }}</h1>
            <a href="{{ route('account.complaints.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('File a complaint') }}</a>
        </div>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif

        @if ($complaints->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not filed any complaints.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($complaints as $complaint)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('account.complaints.show', $complaint) }}" class="font-medium text-brand-700 hover:underline">{{ $complaint->subject }}</a>
                            <span class="block text-sm text-slate-600">{{ $complaint->complaint_number }} · {{ $complaint->shop?->name ?? __('The mall') }} · {{ $complaint->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $complaint->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
