@php
    $shopStatus = $shop->status->value;
    $areaLinks = [
        'stock' => ['workspace.stock.index', __('Stock')],
        'orders' => ['workspace.orders.index', __('Orders and sales')],
        'promotions' => ['workspace.promotions.index', __('Promotions')],
        'support' => ['workspace.support.index', __('Customer support')],
        'maintenance' => ['workspace.maintenance.index', __('Maintenance requests')],
    ];
@endphp

<x-layouts.customer :title="__('Workspace').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Shop workspace') }} · {{ $shop->mall->name }}</p>
        <h1 class="text-2xl font-semibold">{{ $shop->name }}</h1>

        <p class="mt-2">
            <span @class([
                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                'bg-emerald-100 text-emerald-900' => $shopStatus === 'active',
                'bg-amber-100 text-amber-900' => in_array($shopStatus, ['draft', 'closed'], true),
                'bg-slate-200 text-slate-700' => $shopStatus === 'archived',
            ])>{{ ['draft' => __('Waiting for mall approval'), 'active' => __('Active'), 'closed' => __('Suspended'), 'archived' => __('Archived')][$shopStatus] }}</span>
            @if ($function)
                <span class="ml-2 text-sm text-slate-600">{{ __('Your function: :function', ['function' => $function->label()]) }}</span>
            @endif
        </p>

        @if ($shopStatus !== 'active')
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                @if ($shopStatus === 'draft')
                    {{ __('The mall has not approved this shop yet. Stock, orders, promotions and support open once it is active. You can already complete the profile.') }}
                @elseif ($shopStatus === 'closed')
                    {{ __('The mall suspended this shop. Contact the mall office to reopen it.') }}
                @else
                    {{ __('This shop is archived.') }}
                @endif
                @if ($application?->decision_reason)
                    <span class="block mt-1"><span class="font-medium">{{ __('Reason') }}:</span> {{ $application->decision_reason }}</span>
                @endif
            </p>
        @endif

        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            <li><a href="{{ route('workspace.catalogue.index', $shop) }}" class="block rounded-xl border border-slate-200 bg-white p-4 font-medium hover:border-brand-500">{{ __('Catalogue') }}</a></li>
            <li><a href="{{ route('workspace.reviews.index', $shop) }}" class="block rounded-xl border border-slate-200 bg-white p-4 font-medium hover:border-brand-500">{{ __('Reviews') }}</a></li>
            @foreach ($areas as $area)
                @if (Route::has($areaLinks[$area->value][0]))
                    <li><a href="{{ route($areaLinks[$area->value][0], $shop) }}" class="block rounded-xl border border-slate-200 bg-white p-4 font-medium hover:border-brand-500">{{ $areaLinks[$area->value][1] }}</a></li>
                @endif
            @endforeach
            @can('manage-shop', $shop)
                <li><a href="{{ route('workspace.profile.edit', $shop) }}" class="block rounded-xl border border-slate-200 bg-white p-4 font-medium hover:border-brand-500">{{ __('Shop profile and opening hours') }}</a></li>
                <li><a href="{{ route('workspace.staff.index', $shop) }}" class="block rounded-xl border border-slate-200 bg-white p-4 font-medium hover:border-brand-500">{{ __('Staff') }}</a></li>
            @endcan
        </ul>
    </div>
</x-layouts.customer>
