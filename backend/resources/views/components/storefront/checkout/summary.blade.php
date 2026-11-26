{{--
    Checkout order summary (Storefront additions 98:100435 / m 98:100610): lines (56 px
    image, name, Qty, line total, issue message), coupon (spec 08: input + Apply / applied
    box), Items subtotal (incl. fixed shipping), Shipping · carrier (or Free), Discount
    CODE, Total, and the delivery estimate. Re-rendered by `storefront.checkout.fragment`
    when the address (= pricing country) or the coupon changes.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $shipping = $cart['shipping'];
    $coupon = $cart['coupon'];
    $delivery = $cart['delivery'];
    $carrier = $delivery['company_name'] ?? $shipping['rate'] ?? null;
@endphp
<div class="grid gap-3.5 overflow-hidden rounded-sf-card border border-sf-border bg-sf-surface p-4 lg:p-6" data-checkout-summary>
    <h2 class="text-lg font-semibold leading-[26px] text-sf-ink lg:text-lg">{{ __st('checkout.summary') }}</h2>
    <ul class="grid gap-3.5" role="list">
        @foreach ($cart['lines'] as $line)
            <li class="flex items-center gap-3">
                <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-lg bg-[#f5f5f5]">
                    @if ($line['image_url'])
                        <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain bg-media" loading="lazy" decoding="async">
                    @else
                        <x-ui::icon name="image" :size="22" class="text-eco-semi" />
                    @endif
                </span>
                <span class="grid min-w-0 flex-1 gap-0.5">
                    <span class="truncate text-sm font-medium leading-5 text-sf-ink">{{ $line['name'] }}</span>
                    <span class="truncate text-xs leading-4 text-sf-muted">{{ collect([$line['variant'], __st('cart.qty', ['qty' => $line['quantity']])])->filter()->implode(' · ') }}</span>
                    @if ($line['issue'] !== null)
                        <span class="text-xs text-sf-danger">{{ $line['message'] }}</span>
                    @endif
                </span>
                <span class="shrink-0 text-sm font-medium leading-5 text-sf-ink">{{ StorefrontCart::price($line['total']) }}</span>
            </li>
        @endforeach
    </ul>

    <x-sf::checkout.coupon :coupon="$coupon" variant="checkout" />

    <dl class="grid gap-3.5 text-sm leading-5">
        <div class="flex items-start gap-3">
            <dt class="min-w-0 flex-1 text-sf-muted">{{ __st('checkout.items_subtotal') }}</dt>
            <dd class="font-medium text-sf-ink">{{ StorefrontCart::price($totals['items_total']) }}</dd>
        </div>
        <div class="flex items-start gap-3">
            <dt class="min-w-0 flex-1 text-sf-muted">{{ $carrier ? __st('checkout.shipping_via', ['carrier' => $carrier]) : __st('checkout.shipping_label') }}</dt>
            <dd class="font-medium text-sf-ink">
                @if (! $shipping['available'])
                    <span class="text-sf-danger">—</span>
                @elseif ($shipping['free'])
                    {{ __st('checkout.free') }}
                @else
                    {{ StorefrontCart::price($totals['shipping']) }}
                @endif
            </dd>
        </div>
        @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
            <div class="flex items-start gap-3">
                <dt class="min-w-0 flex-1 text-sf-muted">{{ __st('checkout.discount_code', ['code' => $coupon['code']]) }}</dt>
                <dd class="font-medium text-[#16a34a]">− {{ StorefrontCart::price($totals['coupon_discount']) }}</dd>
            </div>
        @endif
        <div class="flex items-center gap-3 text-sf-ink">
            <dt class="min-w-0 flex-1 text-base font-medium leading-6">{{ __st('checkout.total') }}</dt>
            <dd class="text-lg font-semibold leading-[26px]" data-checkout-total>{{ StorefrontCart::price($totals['grand_total']) }}</dd>
        </div>
    </dl>

    @if ($delivery !== null && $shipping['available'])
        <p class="flex items-center gap-2 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[13px] font-medium text-[#111827]">
            <x-ui::icon name="calendar" :size="16" class="shrink-0 text-[#6b7280]" />
            {{ __st('checkout.estimated_delivery', ['from' => \Illuminate\Support\Carbon::parse($delivery['min_date'])->translatedFormat('j M'), 'to' => \Illuminate\Support\Carbon::parse($delivery['max_date'])->translatedFormat('j M')]) }}
        </p>
    @endif
</div>
