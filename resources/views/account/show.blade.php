<x-layouts.customer :title="__('Your account')">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>

        <dl class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            <div class="flex justify-between gap-4 px-5 py-3">
                <dt class="text-slate-500">{{ __('Name') }}</dt>
                <dd class="font-medium">{{ $user->display_name }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-5 py-3">
                <dt class="text-slate-500">{{ __('Email') }}</dt>
                <dd class="font-medium">{{ $user->email ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-5 py-3">
                <dt class="text-slate-500">{{ __('Mobile number') }}</dt>
                <dd class="font-medium">{{ $user->phone ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-5 py-3">
                <dt class="text-slate-500">{{ __('Member since') }}</dt>
                <dd class="font-medium">{{ $user->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</dd>
            </div>
        </dl>
    </div>
</x-layouts.customer>
