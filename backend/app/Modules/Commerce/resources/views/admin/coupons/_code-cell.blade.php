@php
    $reach = $coupon->store_scope === \App\Modules\Commerce\Enums\PromotionScope::SelectedStores
        ? trans_choice('commerce::coupons.labels.stores_count', $coupon->tenants->count(), ['count' => $coupon->tenants->count()])
        : __('commerce::promotions.all_stores');
    $where = $coupon->countries->isEmpty() ? __('commerce::promotions.all_countries') : $coupon->countries->pluck('iso2')->implode(', ');
@endphp
<div class="min-w-0">
    <p class="font-medium text-ink" dir="ltr">{{ $coupon->code }}</p>
    <p class="truncate text-xs text-ink-secondary">{{ $coupon->first_order_only ? __('commerce::coupons.first_order').' · ' : '' }}{{ $reach }} · {{ $where }}</p>
</div>
