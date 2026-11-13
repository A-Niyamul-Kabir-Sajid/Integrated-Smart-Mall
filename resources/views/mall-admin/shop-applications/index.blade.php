<x-layouts.customer :title="__('Shop applications').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Shop applications') }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($applications->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No applications yet.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($applications as $application)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $application->shop->name }}</p>
                                <p class="text-sm text-slate-600">{{ $application->applicant->display_name }} · {{ $application->applicant->email ?? '—' }}</p>
                                @if ($application->message)
                                    <p class="mt-1 text-sm text-slate-700">{{ $application->message }}</p>
                                @endif
                                @if ($application->decision_reason)
                                    <p class="mt-1 text-sm text-slate-700"><span class="font-medium">{{ __('Reason') }}:</span> {{ $application->decision_reason }}</p>
                                @endif
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ ['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')][$application->status->value] }}</span>
                        </div>

                        @if ($application->status->value === 'pending')
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <form method="POST" action="{{ route('shop-applications.admin.approve', [$mall, $application]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('shop-applications.admin.reject', [$mall, $application]) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label for="reject-{{ $application->getKey() }}" class="block text-xs font-medium">{{ __('Reason for rejecting') }}</label>
                                        <input id="reject-{{ $application->getKey() }}" name="reason" required minlength="5" maxlength="1000" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                                    </div>
                                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Reject') }}</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $applications->links() }}</div>
        @endif

        <section class="mt-10" aria-labelledby="shops-heading">
            <h2 id="shops-heading" class="text-xl font-semibold">{{ __('Shops in the mall') }}</h2>
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
                @forelse ($shops as $shop)
                    <li class="flex flex-wrap items-end justify-between gap-3 px-5 py-3">
                        <span><span class="font-medium">{{ $shop->name }}</span> <span class="text-slate-500">· {{ $shop->status->value === 'active' ? __('Active') : __('Suspended') }}</span></span>
                        @if ($shop->status->value === 'active')
                            <form method="POST" action="{{ route('shop-applications.admin.suspend', [$mall, $shop]) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label for="suspend-{{ $shop->getKey() }}" class="block text-xs font-medium">{{ __('Reason for suspending') }}</label>
                                    <input id="suspend-{{ $shop->getKey() }}" name="reason" required minlength="5" maxlength="1000" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                                </div>
                                <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Suspend') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('shop-applications.admin.reactivate', [$mall, $shop]) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Reactivate') }}</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-600">{{ __('No active shops yet.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.customer>
