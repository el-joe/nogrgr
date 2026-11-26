{{--
    Souqify "Order Summary" card (Figma 1:75039 right column): Subtotal, item discount,
    shipping estimate, coupon discount, big accent Total, "Have a promo code?" field + Apply,
    "Proceed to Checkout", secure-checkout line, Free delivery / returns mini cards and the
    payment badges. Re-rendered with the cart body by `storefront.cart.fragment`.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $empty = $cart['lines'] === [];
    $coupon = $cart['coupon'];
    $shipping = $cart['shipping'];
    $blocked = StorefrontCart::blocked($cart);
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges();
@endphp
<div class="sq-summary" data-cart-summary>
    <h2 class="text-2xl font-medium text-sq-ink lg:text-[28px]">{{ __st('souqify_pages.cart.order_summary') }}</h2>
    <dl class="grid gap-3 text-sm text-sf-muted">
        <div class="flex items-center justify-between gap-3"><dt>{{ __st('souqify_pages.cart.subtotal') }}</dt><dd class="text-sq-ink">{{ StorefrontCart::price($totals['subtotal']) }}</dd></div>
        @if (StorefrontCart::positive($totals['flash_discount']))
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('cart.item_discount') }}</dt><dd class="text-sq-green">-{{ StorefrontCart::price($totals['flash_discount']) }}</dd></div>
        @endif
        @if ($shipping['available'] && ! $empty)
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('souqify_pages.cart.shipping_estimate') }}</dt><dd class="text-sq-ink">{{ $shipping['free'] ? __st('checkout.free') : StorefrontCart::price($totals['shipping']) }}</dd></div>
        @endif
        @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('cart.coupon_discount', ['code' => $coupon['code']]) }}</dt><dd class="text-sq-green">-{{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
        @endif
    </dl>
    <p class="flex items-center justify-between gap-3 border-t border-sq-line pt-4 text-base text-sq-ink"><span>{{ __st('cart.total') }}</span><span class="text-[28px] font-semibold text-sq-price" data-cart-total>{{ StorefrontCart::price($totals['grand_total']) }}</span></p>
    @unless ($empty)
        <div class="grid gap-2">
            <p class="text-xs text-sf-muted">{{ __st('souqify_pages.cart.promo_question') }}</p>
            <x-sf::souqify.inner.coupon :coupon="$coupon" />
        </div>
    @endunless
    @if ($empty)
        <a href="{{ route('storefront.home') }}" class="sq-btn sq-btn-primary mo-press h-12 w-full">{{ __st('souqify_pages.cart.start_shopping') }}</a>
    @else
        <a href="{{ route('storefront.checkout') }}" @class(['sq-btn sq-btn-primary mo-press h-12 w-full text-base', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout><x-ui::icon name="cart" :size="20" />{{ __st('souqify_pages.cart.proceed') }}</a>
        <p class="text-center text-[11px] text-sf-muted">{{ __st('souqify_pages.cart.secure_line') }} · {{ StorefrontCart::price($totals['grand_total']) }}</p>
    @endif
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
