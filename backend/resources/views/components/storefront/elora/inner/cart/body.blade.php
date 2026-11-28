{{--
    Elora cart body (Figma 1:17541 left column / m 1:22029; empty m 1:23315): free-shipping
    meter, guest sign-in prompt, select-all row with "Remove selected", the lines; on phones
    the sticky total + "Check out" bar. Re-rendered by `storefront.cart.fragment` after every
    Store API mutation (commerce/cart-page.js) — same hooks as x-sf::cart.body.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $lines = $cart['lines'];
    $count = count($lines);
    $blocked = StorefrontCart::blocked($cart);
    $saved = StorefrontCart::positive($cart['totals']['flash_discount']) ? $cart['totals']['flash_discount'] : null;
@endphp
<div class="elo-cart-body" data-cart-body data-items-count="{{ $cart['items_count'] }}">
    @if ($count === 0)
        <div class="elo-empty elo-cart-empty min-h-[50vh] content-center" data-reveal="fade-up">
            <span class="elo-empty-icon"><x-ui::icon name="cart" :size="44" /></span>
            <h2>{{ __st('elora_pages.cart.empty_title') }}</h2>
            <p>{{ __st('elora_pages.cart.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink mo-press mt-2 px-7 text-base">{{ __st('elora_pages.cart.continue') }}<x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" /></a>
        </div>
    @else
        <x-sf::elora.inner.cart.progress :free="$cart['free_shipping']" :shipping="$cart['shipping']" cart class="elo-cart-free" />

        @guest('customer')
            @if (Route::has('storefront.account.login'))
                <a href="{{ route('storefront.account.login', ['redirect' => '/cart']) }}" class="elo-cart-login"><x-ui::icon name="elo-person" :size="24" />{{ __st('elora_pages.cart.login_prompt') }}</a>
            @endif
        @endguest

        <div class="elo-cart-sel" data-cart-selection="any">
            <div class="elo-cart-selhead">
                <label class="inline-flex items-center gap-4">
                    <x-sf::elora.check checked data-cart-select-all />
                    <span>{{ __st('cart.select_all') }} (<span data-cart-total-count>{{ $count }}</span>)</span>
                </label>
                <button type="button" class="hidden items-center gap-1 text-xs text-sf-danger hover:underline [[data-has-selection]_&]:inline-flex" data-cart-remove-selected data-confirm-title="{{ __st('elora_pages.cart.remove_selected_title') }}" data-confirm-text="{{ __st('elora_pages.cart.remove_selected_body') }}" data-confirm-button="{{ __st('cart.remove_selected') }}">
                    <x-ui::icon name="trash" :size="14" />{{ __st('cart.remove_selected') }}
                </button>
            </div>
            <ul class="elo-cart-lines" role="list" data-cart-lines>
                @foreach ($lines as $line)
                    <x-sf::elora.inner.cart.line :line="$line" :delivery="$cart['delivery'] ?? null" />
                @endforeach
            </ul>
        </div>

        @if ($blocked)
            <x-sf::alert tone="danger">{{ __st('cart.cannot_checkout') }}</x-sf::alert>
        @endif

        {{-- Phone total + "Check out" (m 1:22029) --}}
        <div class="elo-checkout-bar lg:hidden" data-checkout-bar>
            @if ($saved)
                <span class="elo-bar-save">{{ __st('elora_pages.cart.you_save', ['amount' => StorefrontCart::price($saved)]) }}</span>
            @endif
            <div class="elo-bar-row">
                <p class="elo-bar-total">{{ __st('cart.total') }}<span data-cart-total>{{ StorefrontCart::price($cart['totals']['grand_total']) }}</span></p>
                <a href="{{ route('storefront.checkout') }}" @class(['elo-btn elo-btn-primary elo-bar-btn mo-press', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('elora_pages.cart.check_out') }}<x-ui::icon name="elo-chevron-bar" :size="18" /></a>
            </div>
        </div>
    @endif
</div>
