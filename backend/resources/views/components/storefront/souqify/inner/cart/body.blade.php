{{--
    Souqify cart body (Figma 1:75039 left column / m 1:76392; empty 137:112552 / m(ar)
    137:112713): free-shipping bar (spans both columns on desktop), guest sign-in prompt, the
    line cards; on phones the sticky total + "Proceed to Checkout" bar. Re-rendered by
    `storefront.cart.fragment` after every Store API mutation (commerce/cart-page.js) — same
    hooks as x-sf::cart.body.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $lines = $cart['lines'];
    $blocked = StorefrontCart::blocked($cart);
    $saved = StorefrontCart::positive($cart['totals']['flash_discount']) ? $cart['totals']['flash_discount'] : null;
    $favorites = Route::has('storefront.favorites') ? route('storefront.favorites') : null;
@endphp
<div class="grid gap-4" data-cart-body data-items-count="{{ $cart['items_count'] }}">
    @if ($lines === [])
        <div class="sq-empty min-h-[40vh] content-center">
            <span class="sq-empty-icon"><x-ui::icon name="cart" :size="44" /></span>
            <h2>{{ __st('souqify_pages.cart.empty_title') }}</h2>
            <p>{{ __st('souqify_pages.cart.empty_body') }}</p>
            <div class="mt-2 flex flex-wrap justify-center gap-3">
                <a href="{{ route('storefront.home') }}" class="sq-btn sq-btn-primary mo-press rounded-full px-6">{{ __st('souqify_pages.cart.start_shopping') }}</a>
                @if ($favorites)
                    <a href="{{ $favorites }}" class="sq-btn sq-btn-outline mo-press rounded-full px-6">{{ __st('souqify_pages.cart.view_favorites') }}</a>
                @endif
            </div>
        </div>
    @else
        <x-sf::souqify.inner.cart.progress :free="$cart['free_shipping']" class="sq-shipbar-wide" />

        @guest('customer')
            @if (Route::has('storefront.account.login'))
                <a href="{{ route('storefront.account.login', ['redirect' => '/cart']) }}" class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 text-sm text-sq-ink transition-shadow hover:shadow-md"><x-ui::icon name="user" :size="18" class="text-sq-primary" />{{ __st('souqify_pages.cart.login_prompt') }}<x-ui::icon name="chevron-right" :size="16" class="ms-auto rtl:-scale-x-100" /></a>
            @endif
        @endguest

        <ul class="grid gap-4" role="list" data-cart-lines>
            @foreach ($lines as $line)
                <x-sf::souqify.inner.cart.line :line="$line" />
            @endforeach
        </ul>

        @if ($blocked)
            <x-sf::alert tone="danger">{{ __st('cart.cannot_checkout') }}</x-sf::alert>
        @endif

        {{-- Phone total + checkout (m 1:76392) --}}
        <div class="sq-checkout-bar lg:hidden">
            @if ($saved)
                <span class="sq-save-tag absolute -top-[22px] start-4">{{ __st('souqify_pages.cart.you_save', ['amount' => StorefrontCart::price($saved)]) }}</span>
            @endif
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <p class="grid text-xs text-sf-muted">{{ __st('cart.total') }}<span class="text-lg font-semibold text-sq-price" data-cart-total>{{ StorefrontCart::price($cart['totals']['grand_total']) }}</span></p>
                <a href="{{ route('storefront.checkout') }}" @class(['sq-btn sq-btn-primary mo-press h-12 max-w-[240px] flex-1', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout><x-ui::icon name="cart" :size="18" />{{ __st('souqify_pages.cart.proceed') }}</a>
            </div>
        </div>
    @endif
</div>
