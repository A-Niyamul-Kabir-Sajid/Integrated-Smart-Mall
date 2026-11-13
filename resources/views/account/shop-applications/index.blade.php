<x-layouts.customer :title="__('Your shop applications')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Your shop applications') }}</h1>
            <a href="{{ route('shop-applications.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Open a shop') }}</a>
        </div>

        @if ($applications->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not applied to open a shop yet.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($applications as $application)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">{{ $application->shop->name }} <span class="text-sm font-normal text-slate-500">· {{ $application->mall->name }}</span></p>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-amber-100 text-amber-900' => $application->status->value === 'pending',
                                'bg-emerald-100 text-emerald-900' => $application->status->value === 'approved',
                                'bg-rose-100 text-rose-900' => $application->status->value === 'rejected',
                            ])>{{ ['pending' => __('Waiting for a decision'), 'approved' => __('Approved'), 'rejected' => __('Not approved')][$application->status->value] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Sent :date', ['date' => $application->created_at->timezone('Asia/Dhaka')->format('d M Y')]) }}</p>
                        @if ($application->decision_reason)
                            <p class="mt-2 text-sm text-slate-700"><span class="font-medium">{{ __('Reason') }}:</span> {{ $application->decision_reason }}</p>
                        @endif
                        @if ($application->status->value !== 'rejected')
                            <p class="mt-2"><a href="{{ route('workspace.dashboard', $application->shop_id) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Open the shop workspace') }}</a></p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
