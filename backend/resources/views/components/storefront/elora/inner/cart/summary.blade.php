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
    <h2 class="text-lg font-medium text-elo-ink">{{ __st('cart.summary') }}</h2>
    <dl class="grid gap-3 text-sm text-elo-ink">
        <div class="flex items-center justify-between gap-3"><dt>{{ __st('elora_pages.cart.subtotal') }}</dt><dd>{{ StorefrontCart::price($totals['subtotal']) }}</dd></div>
        @if (StorefrontCart::positive($totals['flash_discount']))
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('cart.item_discount') }}</dt><dd class="text-elo-primary">-{{ StorefrontCart::price($totals['flash_discount']) }}</dd></div>
        @endif
        @if ($shipping['available'] && ! $empty)
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('elora_pages.cart.shipping_fee') }}</dt><dd>{{ $shipping['free'] ? __st('checkout.free') : StorefrontCart::price($totals['shipping']) }}</dd></div>
        @endif
        @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
            <div class="flex items-center justify-between gap-3"><dt>{{ __st('cart.coupon_discount', ['code' => $coupon['code']]) }}</dt><dd class="text-elo-primary">-{{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
        @endif
    </dl>
    @unless ($empty)
        <x-sf::elora.inner.coupon :coupon="$coupon" />
    @endunless
    <div class="grid gap-2">
        <p class="flex items-center justify-between gap-3 text-base font-medium text-elo-ink"><span>{{ __st('cart.total') }}</span><span data-cart-total>{{ StorefrontCart::price($totals['grand_total']) }}</span></p>
        <p class="text-xs leading-relaxed text-[#5e5e5e]">{{ __st('cart.total_note') }}</p>
    </div>
    @if ($empty)
        <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink mo-press h-11 w-full">{{ __st('elora_pages.cart.continue') }}</a>
    @else
        <a href="{{ route('storefront.checkout') }}" @class(['elo-btn elo-btn-ink mo-press h-11 w-full', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('cart.to_checkout', ['total' => StorefrontCart::price($totals['grand_total'])]) }}</a>
    @endif
</div>
