@php($status = $complaint->status->value)

<x-layouts.customer :title="$complaint->subject">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.complaints.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('My complaints') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $complaint->subject }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $complaint->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $complaint->complaint_number }} · {{ $complaint->mall->name }} · {{ $complaint->shop?->name ?? __('The mall') }} · {{ $complaint->category->label() }}</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <p class="mt-4 whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4">{{ $complaint->description }}</p>
        @if ($complaint->assignee)
            <p class="mt-2 text-sm text-slate-600">{{ __('Looked after by') }}: <span class="font-medium">{{ $complaint->assignee->display_name }}</span></p>
        @endif
        @if ($complaint->resolution_note && in_array($status, ['resolved', 'closed', 'rejected'], true))
            <p class="mt-3 text-sm"><span class="font-medium">{{ $status === 'rejected' ? __('Why it was not accepted') : __('What the mall did') }}:</span> {{ $complaint->resolution_note }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-end gap-3">
            @if ($status === 'resolved')
                <form method="POST" action="{{ route('account.complaints.confirm', $complaint) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('That solved it, close the complaint') }}</button>
                </form>
                <form method="POST" action="{{ route('account.complaints.reopen', $complaint) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="reopen-note" class="block text-xs font-medium">{{ __('Still wrong? Say what') }}</label>
                        <input id="reopen-note" name="note" required minlength="3" maxlength="500" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Reopen') }}</button>
                </form>
            @endif
            @if (in_array($status, ['open', 'assigned', 'in_progress'], true))
                <form method="POST" action="{{ route('account.complaints.withdraw', $complaint) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Withdraw this complaint') }}</button>
                </form>
            @endif
        </div>

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($complaint->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\MallOwnerAdmin\Enums\ComplaintStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
