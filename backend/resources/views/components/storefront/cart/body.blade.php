{{--
    Cart body (left column of 1:1348 / m 1:3210), re-rendered by `storefront.cart.fragment`
    after every Store API mutation: free-shipping progress, select-all row with the
    All / Selected chips, the lines, and on phones the checkout button + trust strip.
    Empty cart → the 1:1240 empty state.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $lines = $cart['lines'];
    $count = count($lines);
@endphp
<div class="grid gap-6" data-cart-body data-items-count="{{ $cart['items_count'] }}">
    @if ($count === 0)
        @if ($cart['free_shipping'] !== null)
            <p class="flex items-center gap-2 rounded bg-[#ffebeb] px-2 py-2 text-sm tracking-[0.5px] text-eco-black"><x-ui::icon name="truck" :size="20" />{{ __st('cart.free_shipping_banner') }}</p>
        @endif
        <div class="grid justify-items-center gap-8 py-6 text-center lg:py-4">
            <div class="flex items-center gap-6">
                <x-ui::icon name="cart" :size="72" class="text-eco-gray" />
                <p class="text-start text-sm font-medium tracking-[0.5px] text-eco-black">{{ __st('cart.empty_title') }}<br>{{ __st('cart.empty_body') }}</p>
            </div>
            {{-- Outlined on phones (m 1:3329), dark on desktop (1:1240). --}}
            <a href="{{ route('storefront.best-sellers') }}" class="eco-pill-dark mo-press h-10 min-w-[240px] text-sm max-lg:!border max-lg:!border-eco-black max-lg:!bg-white max-lg:!text-eco-black">{{ __st('cart.see_trending') }}</a>
        </div>
        <div class="lg:hidden"><x-sf::cart.trust compact /></div>
    @else
        <x-sf::cart.progress :free="$cart['free_shipping']" />

        <div class="grid gap-6" data-cart-selection>
            <div class="flex items-center justify-between gap-3 border-b border-eco-semi pb-4">
                <label class="inline-flex items-center gap-4 text-sm font-medium tracking-[0.5px] text-eco-black">
                    <input type="checkbox" class="sf-check size-5" checked data-cart-select-all>
                    <span>{{ __st('cart.select_all') }} (<span data-cart-total-count>{{ $count }}</span>)</span>
                </label>
                <div class="flex items-center gap-2">
                    <button type="button" class="hidden items-center gap-1 rounded-2xl px-2 py-1 text-xs tracking-[0.5px] text-eco-red hover:underline [[data-has-selection]_&]:inline-flex" data-cart-remove-selected>
                        <x-ui::icon name="trash" :size="14" />{{ __st('cart.remove_selected') }}
                    </button>
                    <div class="flex items-center gap-2" role="group" aria-label="{{ __st('cart.filter') }}">
                        <button type="button" class="rounded-2xl border border-eco-gray px-3 py-1 text-xs tracking-[0.5px] text-eco-gray aria-pressed:border-eco-black aria-pressed:text-eco-black" aria-pressed="false" data-cart-filter="all">{{ __st('cart.all') }} ({{ $count }})</button>
                        <button type="button" class="rounded-2xl border border-eco-gray px-3 py-1 text-xs tracking-[0.5px] text-eco-gray aria-pressed:border-eco-black aria-pressed:text-eco-black" aria-pressed="true" data-cart-filter="selected">{{ __st('cart.selected') }} (<span data-cart-selected-count>{{ $count }}</span>)</button>
                    </div>
                </div>
            </div>
            <ul class="grid gap-4" role="list" data-cart-lines>
                @foreach ($lines as $line)
                    <x-sf::cart.line :line="$line" />
                @endforeach
            </ul>
        </div>

        @if (StorefrontCart::blocked($cart))
            <x-sf::alert tone="danger" class="lg:hidden">{{ __st('cart.cannot_checkout') }}</x-sf::alert>
        @endif

        <div class="grid gap-6 lg:hidden">
            <a href="{{ route('storefront.checkout') }}" @class(['eco-pill-dark mo-press h-10 w-full text-base', 'pointer-events-none opacity-50' => StorefrontCart::blocked($cart)]) @if (StorefrontCart::blocked($cart)) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('cart.checkout') }}</a>
            <x-sf::cart.trust compact />
        </div>
    @endif
</div>
