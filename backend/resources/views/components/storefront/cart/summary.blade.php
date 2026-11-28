{{--
    Order summary (Ecommet 1:1491 + 1:1434; empty 1:1240): Item total (struck when
    discounted), Item discount, coupon (input + Apply, or the applied code + Remove),
    shipping / coupon rows when they apply, Total, note and "{total} to checkout".
    Re-rendered with the cart body by `storefront.cart.fragment`.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $empty = $cart['lines'] === [];
    $flash = StorefrontCart::positive($totals['flash_discount']);
    $coupon = $cart['coupon'];
    $shipping = $cart['shipping'];
@endphp
<div class="grid gap-6" data-cart-summary>
    <div class="grid gap-2">
        <h2 class="pb-4 text-xl font-medium text-eco-black">{{ __st('cart.summary') }}</h2>
        @unless ($empty)
            <dl class="grid gap-4 pb-4 text-base text-black">
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __st('cart.item_total') }}</dt>
                    <dd @class(['line-through' => $flash])>{{ StorefrontCart::price($totals['subtotal']) }}</dd>
                </div>
                @if ($flash)
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ __st('cart.item_discount') }}</dt>
                        <dd class="text-[#ed181a]">-{{ StorefrontCart::price($totals['flash_discount']) }}</dd>
                    </div>
                @endif
                @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ __st('cart.coupon_discount', ['code' => $coupon['code']]) }}</dt>
                        <dd class="text-[#ed181a]">-{{ StorefrontCart::price($totals['coupon_discount']) }}</dd>
                    </div>
                @endif
                @if ($shipping['available'])
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ __st('cart.shipping') }}</dt>
                        <dd>{{ $shipping['free'] ? __st('checkout.free') : StorefrontCart::price($totals['shipping']) }}</dd>
                    </div>
                @elseif ($shipping['reason'] === 'no_band')
                    <div class="text-sm text-sf-danger" data-shipping-unavailable>{{ $shipping['message'] }}</div>
                @endif
            </dl>
            <x-sf::checkout.coupon :coupon="$coupon" variant="cart" />
        @endunless
    </div>
    <div class="grid gap-6">
        <div class="grid gap-3 text-base">
            <p class="flex items-center justify-between gap-3 font-medium text-black"><span>{{ __st('cart.total') }}</span><span data-cart-total>{{ StorefrontCart::price($totals['grand_total']) }}</span></p>
            <p class="text-[#707070]">{{ __st('cart.total_note') }}</p>
        </div>
        @if ($empty)
            <a href="{{ route('storefront.best-sellers') }}" class="eco-pill-dark mo-press h-[50px] w-full rounded-[32px] text-base font-medium">{{ __st('cart.see_trending') }}</a>
        @else
            @if (StorefrontCart::blocked($cart))
                <x-sf::alert tone="danger">{{ __st('cart.cannot_checkout') }}</x-sf::alert>
            @endif
            <a href="{{ route('storefront.checkout') }}" @class(['eco-pill-dark mo-press h-[50px] w-full rounded-[32px] px-8 text-base font-medium', 'pointer-events-none opacity-50' => StorefrontCart::blocked($cart)]) @if (StorefrontCart::blocked($cart)) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('cart.to_checkout', ['total' => StorefrontCart::price($totals['grand_total'])]) }}</a>
        @endif
    </div>
</div>
