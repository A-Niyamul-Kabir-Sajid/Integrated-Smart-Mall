@props(['provenance'])

@if ($provenance['any'])
    <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
        <span class="font-medium">{{ __('Demonstration data included.') }}</span>
        {{ __(':sales of :sales_total recorded sales, :orders of :orders_total orders and :reviews of :reviews_total reviews in this period are simulated demonstration records, not real customers.', [
            'sales' => $provenance['sales']['simulated'], 'sales_total' => $provenance['sales']['total'],
            'orders' => $provenance['orders']['simulated'], 'orders_total' => $provenance['orders']['total'],
            'reviews' => $provenance['reviews']['simulated'], 'reviews_total' => $provenance['reviews']['total'],
        ]) }}
    </p>
@endif
