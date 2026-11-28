{{--
    Elora order summary card (Figma 1:17541 right column / m 1:22029): Subtotal, item
    discount, shipping fee, coupon, Total + note, "{total} to checkout". Re-rendered with
    the cart body by `storefront.cart.fragment`.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $empty = $cart['lines'] === [];
    $coupon = $cart['coupon'];
    $shipping = $cart['shipping'];
    $blocked = StorefrontCart::blocked($cart);
@endphp
<div class="elo-summary" data-cart-summary>
    <h2 class="elo-sum-title">{{ __st('cart.summary') }}</h2>
    <dl class="elo-sum-rows">
        <div><dt>{{ __st('elora_pages.cart.subtotal') }}</dt><dd>{{ StorefrontCart::price($totals['subtotal']) }}</dd></div>
        @if (StorefrontCart::positive($totals['flash_discount']))
            <div><dt>{{ __st('cart.item_discount') }}</dt><dd class="text-elo-primary">-{{ StorefrontCart::price($totals['flash_discount']) }}</dd></div>
        @endif
        @if ($shipping['available'] && ! $empty)
            <div><dt>{{ __st('elora_pages.cart.shipping_fee') }}</dt><dd>{{ $shipping['free'] ? __st('checkout.free') : StorefrontCart::price($totals['shipping']) }}</dd></div>
        @elseif (! $empty && $shipping['reason'] === 'no_band')
            <div class="text-sm text-sf-danger" data-shipping-unavailable>{{ $shipping['message'] }}</div>
        @endif
        @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
            <div><dt>{{ __st('cart.coupon_discount', ['code' => $coupon['code']]) }}</dt><dd class="text-elo-primary">-{{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
        @endif
    </dl>
    @unless ($empty)
        <x-sf::elora.inner.coupon :coupon="$coupon" />
    @endunless
    <div class="elo-sum-total">
        <p><span>{{ __st('cart.total') }}</span><span data-cart-total>{{ StorefrontCart::price($totals['grand_total']) }}</span></p>
        <p class="elo-sum-note">{{ __st('cart.total_note') }}</p>
    </div>
    @if ($empty)
        <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink elo-sum-btn mo-press">{{ __st('elora_pages.cart.continue') }}</a>
    @else
        <a href="{{ route('storefront.checkout') }}" @class(['elo-btn elo-btn-ink elo-sum-btn mo-press', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('cart.to_checkout', ['total' => StorefrontCart::price($totals['grand_total'])]) }}</a>
    @endif
</div>
