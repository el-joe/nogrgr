{{-- Code + reach of a vendor coupon row (VEN-040): "All products · SA, AE", hidden tag on central rows. --}}
@php
    $where = $coupon->countryIds === [] ? __('commerce::promotions.all_countries') : collect($coupon->countryIds)->map(fn ($id) => $codes[$id] ?? '#'.$id)->implode(', ');
@endphp
<div class="min-w-0">
    <p class="flex items-center gap-2 font-medium text-ink">
        <span dir="ltr">{{ $coupon->code }}</span>
        @if ($coupon->hidden)
            <x-ui::badge tone="neutral" :title="__('commerce::vendor_promotions.hidden_hint')">{{ __('commerce::vendor_promotions.hidden') }}</x-ui::badge>
        @endif
    </p>
    <p class="truncate text-xs text-ink-secondary">{{ $coupon->firstOrderOnly ? __('commerce::coupons.first_order').' · ' : '' }}{{ __('commerce::vendor_promotions.coupons.all_products') }} · {{ $where }}</p>
</div>
