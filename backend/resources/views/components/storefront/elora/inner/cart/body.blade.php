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
<div class="grid gap-5" data-cart-body data-items-count="{{ $cart['items_count'] }}">
    @if ($count === 0)
        <div class="elo-empty min-h-[50vh] content-center" data-reveal="fade-up">
            <span class="elo-empty-icon"><x-ui::icon name="cart" :size="44" /></span>
            <h2>{{ __st('elora_pages.cart.empty_title') }}</h2>
            <p>{{ __st('elora_pages.cart.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink mo-press mt-2 px-7 text-base">{{ __st('elora_pages.cart.continue') }}<x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" /></a>
        </div>
    @else
        <x-sf::elora.inner.cart.progress :free="$cart['free_shipping']" />

        @guest('customer')
            @if (Route::has('storefront.account.login'))
                <a href="{{ route('storefront.account.login', ['redirect' => '/cart']) }}" class="flex items-center gap-3 rounded-md border border-elo-line bg-elo-soft px-3 py-2.5 text-sm text-elo-ink hover:border-elo-ink"><x-ui::icon name="user" :size="18" />{{ __st('elora_pages.cart.login_prompt') }}</a>
            @endif
        @endguest

        <div class="grid" data-cart-selection>
            <div class="flex items-center justify-between gap-3 border-b border-elo-line pb-3">
                <label class="inline-flex items-center gap-3 text-sm text-elo-ink">
                    <input type="checkbox" class="sf-check size-5 accent-[var(--nog-ink)]" checked data-cart-select-all>
                    <span>{{ __st('cart.select_all') }} (<span data-cart-total-count>{{ $count }}</span>)</span>
                </label>
                <div class="flex items-center gap-2">
                    <button type="button" class="hidden items-center gap-1 text-xs text-sf-danger hover:underline [[data-has-selection]_&]:inline-flex" data-cart-remove-selected>
                        <x-ui::icon name="trash" :size="14" />{{ __st('cart.remove_selected') }}
                    </button>
                    <div class="flex items-center gap-2" role="group" aria-label="{{ __st('cart.filter') }}">
                        <button type="button" class="elo-pill elo-pill-ghost h-7 px-3 text-xs" aria-pressed="false" data-cart-filter="all">{{ __st('cart.all') }} ({{ $count }})</button>
                        <button type="button" class="elo-pill elo-pill-ghost h-7 px-3 text-xs" aria-pressed="true" data-cart-filter="selected">{{ __st('cart.selected') }} (<span data-cart-selected-count>{{ $count }}</span>)</button>
                    </div>
                </div>
            </div>
            <ul class="grid" role="list" data-cart-lines>
                @foreach ($lines as $line)
                    <x-sf::elora.inner.cart.line :line="$line" />
                @endforeach
            </ul>
        </div>

        @if ($blocked)
            <x-sf::alert tone="danger">{{ __st('cart.cannot_checkout') }}</x-sf::alert>
        @endif

        {{-- Phone total + "Check out" (m 1:22029) --}}
        <div class="elo-checkout-bar lg:hidden">
            @if ($saved)
                <span class="elo-save-tag absolute -top-[22px] start-4">{{ __st('elora_pages.cart.you_save', ['amount' => StorefrontCart::price($saved)]) }}</span>
            @endif
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <p class="grid text-xs text-sf-muted">{{ __st('cart.total') }}<span class="text-lg font-medium text-elo-ink" data-cart-total>{{ StorefrontCart::price($cart['totals']['grand_total']) }}</span></p>
                <a href="{{ route('storefront.checkout') }}" @class(['elo-btn elo-btn-primary mo-press h-11 flex-1 max-w-[220px]', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('elora_pages.cart.check_out') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
            </div>
        </div>
    @endif
</div>
