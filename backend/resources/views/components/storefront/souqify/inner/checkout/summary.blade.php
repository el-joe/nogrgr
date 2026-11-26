{{--
    Souqify checkout "Order Summary" (Figma 1:75299 right column / m 1:76569 + Storefront
    additions 98:101297 in Souqify mode): 28px title, item rows (image, name, variant · qty,
    estimated delivery in green, line total + struck subtotal, issue), promo code, Subtotal /
    Shipping · carrier / Discount, big "Total due", Free delivery / returns mini cards and the
    payment badges. Re-rendered by `storefront.checkout.fragment` when the address (pricing
    country) or the coupon changes — same hooks as x-sf::checkout.summary.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $shipping = $cart['shipping'];
    $coupon = $cart['coupon'];
    $delivery = $cart['delivery'];
    $carrier = $delivery['company_name'] ?? $shipping['rate'] ?? null;
    $estimate = $delivery !== null && $shipping['available']
        ? __st('checkout.estimated_delivery', ['from' => \Illuminate\Support\Carbon::parse($delivery['min_date'])->translatedFormat('j M'), 'to' => \Illuminate\Support\Carbon::parse($delivery['max_date'])->translatedFormat('j M')])
        : null;
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges();
@endphp
<div class="sq-summary gap-5" data-checkout-summary>
    <h2 class="text-2xl font-medium text-sq-ink lg:text-[28px]">{{ __st('souqify_pages.cart.order_summary') }}</h2>
    <ul class="grid divide-y divide-sq-line" role="list">
        @foreach ($cart['lines'] as $line)
            <li class="flex items-start gap-3 py-4 first:pt-0 lg:gap-4">
                <span class="grid size-[72px] shrink-0 place-items-center overflow-hidden rounded-lg bg-sq-soft lg:size-[78px]">
                    @if ($line['image_url'])
                        <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain bg-media" loading="lazy" decoding="async">
                    @else
                        <x-ui::icon name="image" :size="22" class="text-sq-line" />
                    @endif
                </span>
                <span class="grid min-w-0 flex-1 gap-1">
                    <span class="line-clamp-2 text-sm font-medium text-sq-ink lg:text-base">{{ $line['name'] }}</span>
                    <span class="truncate text-xs text-sf-muted">{{ collect([$line['variant'], __st('cart.qty', ['qty' => $line['quantity']])])->filter()->implode(' | ') }}</span>
                    @if ($line['issue'] !== null)
                        <span class="text-xs text-sf-danger">{{ $line['message'] }}</span>
                    @elseif ($estimate)
                        <span class="text-xs text-sq-green">{{ $estimate }}</span>
                    @endif
                    <span class="flex items-baseline gap-2">
                        <span class="text-base font-medium text-sq-ink">{{ StorefrontCart::price($line['total']) }}</span>
                        @if (StorefrontCart::positive($line['discount']))
                            <del class="text-xs text-sf-muted">{{ StorefrontCart::price($line['subtotal']) }}</del>
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ul>

    <x-sf::souqify.inner.coupon :coupon="$coupon" />

    <dl class="grid gap-3 border-t border-sq-line pt-4 text-sm">
        <div class="flex items-start gap-3"><dt class="flex-1 font-medium text-sq-ink">{{ __st('checkout.items_subtotal') }}</dt><dd class="text-sq-ink">{{ StorefrontCart::price($totals['items_total']) }}</dd></div>
        <div class="flex items-start gap-3">
            <dt class="flex-1 text-xs text-sf-muted">{{ $carrier ? __st('checkout.shipping_via', ['carrier' => $carrier]) : __st('checkout.shipping_label') }}</dt>
            <dd class="text-xs text-sf-muted">
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
            <div class="flex items-start gap-3"><dt class="flex-1 text-sf-muted">{{ __st('checkout.discount_code', ['code' => $coupon['code']]) }}</dt><dd class="text-sq-green">− {{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
        @endif
        <div class="mt-3 flex items-center gap-3 border-t border-sq-line pt-5 text-sq-ink">
            <dt class="flex-1 text-sm font-medium">{{ __st('souqify_pages.checkout.total_due') }}</dt>
            <dd class="text-2xl font-semibold" data-checkout-total>{{ StorefrontCart::price($totals['grand_total']) }}</dd>
        </div>
    </dl>

    <div class="grid grid-cols-2 gap-3">
        <div class="sq-mini-trust"><x-ui::icon name="truck" :size="18" class="text-sq-primary" /><strong>{{ __st('souqify_pages.product.free_delivery') }}</strong><span>{{ __st('cart.trust.guarantee.credit') }}</span></div>
        <div class="sq-mini-trust"><x-ui::icon name="shield" :size="18" class="text-sq-primary" /><strong>{{ __st('souqify_pages.product.easy_returns') }}</strong><span>{{ __st('cart.trust.guarantee.damaged') }}</span></div>
    </div>
    @if ($badges)
        <ul class="flex flex-wrap items-center justify-center gap-2" role="list" aria-label="{{ __st('souqify_pages.cart.payment_methods') }}">
            @foreach ($badges as $badge)
                <li class="sq-pay-chip">{{ __st('shell.payments.'.$badge) }}</li>
            @endforeach
        </ul>
    @endif
</div>
