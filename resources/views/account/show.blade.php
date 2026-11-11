<x-layouts.customer :title="__('Your account')">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ __('Your account') }}</h1>
            <div class="flex flex-wrap gap-2">
                @if (Route::has('account.saved'))
                    <a href="{{ route('account.saved') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Saved') }}</a>
                @endif
                @if (Route::has('account.lists.index'))
                    <a href="{{ route('account.lists.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Shopping lists') }}</a>
                @endif
                <a href="{{ route('account.password.edit') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Change password') }}</a>
                @if (Route::has('account.reviews'))
                    <a href="{{ route('account.reviews') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Your reviews') }}</a>
                @endif
                <a href="{{ route('account.preferences.edit') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Preferences') }}</a>
                <a href="{{ route('account.profile.edit') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Edit profile') }}</a>
            </div>
        </div>

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

        @if (isset($moderatedMalls) && $moderatedMalls->isNotEmpty() && Route::has('moderation.index'))
            <section class="mt-8" aria-labelledby="moderation-heading">
                <h2 id="moderation-heading" class="text-lg font-semibold">{{ __('Malls you administer') }}</h2>
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($moderatedMalls as $mall)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <span class="font-medium">{{ $mall->name }}</span>
                            <a href="{{ route('moderation.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Review moderation') }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($shopMemberships->isNotEmpty())
            <section class="mt-8" aria-labelledby="shops-heading">
                <h2 id="shops-heading" class="text-lg font-semibold">{{ __('Shops you work in') }}</h2>
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($shopMemberships as $membership)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <span><span class="font-medium">{{ $membership->shop->name }}</span> <span class="text-sm text-slate-500">· {{ __($membership->role->name) }}</span></span>
                            <span class="flex gap-4">
                                @if (Route::has('workspace.reviews.index'))
                                    <a href="{{ route('workspace.reviews.index', $membership->shop_id) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Reviews') }}</a>
                                @endif
                                @if (Route::has('workspace.catalogue.index'))
                                    <a href="{{ route('workspace.catalogue.index', $membership->shop_id) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Manage catalogue') }}</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.customer>
