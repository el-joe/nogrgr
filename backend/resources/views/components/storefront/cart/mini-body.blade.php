{{--
    Mini cart content (Ecommet side cart 1:1662): Subtotal, free-shipping note, "{total} to
    checkout", "Go to cart", select all + item tiles (image, price, qty). Rendered by
    `storefront.cart.mini` into the header drawer; empty → short empty state.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $lines = $cart['lines'];
    $free = $cart['free_shipping'];
    $blocked = StorefrontCart::blocked($cart);
@endphp
<div class="grid gap-4" data-cart-body data-items-count="{{ $cart['items_count'] }}">
    @if ($lines === [])
        <div class="grid justify-items-center gap-4 pt-10 text-center">
            <x-ui::icon name="cart" :size="40" class="text-eco-gray" />
            <p class="text-sm tracking-[0.5px] text-eco-black">{{ __st('cart.empty_title') }}</p>
            <a href="{{ route('storefront.best-sellers') }}" class="eco-pill-dark mo-press h-11 w-full rounded-[32px] text-sm">{{ __st('cart.see_trending') }}</a>
        </div>
    @else
        <div class="grid justify-items-center gap-4 border-b border-eco-semi pb-4">
            <div class="grid justify-items-center gap-[3px]">
                <p class="flex items-center gap-2 text-sm tracking-[0.5px] text-eco-black"><x-ui::icon name="cart" :size="24" />{{ __st('cart.subtotal') }}</p>
                <p class="text-base font-medium text-eco-black">{{ StorefrontCart::price($cart['totals']['items_total']) }}</p>
            </div>
            @if ($cart['shipping']['available'] && $cart['shipping']['free'])
                <p class="flex w-full items-center gap-2 rounded-lg bg-[#ffebeb] p-2 text-sm tracking-[0.5px] text-eco-black"><x-ui::icon name="check" :size="16" class="shrink-0 text-eco-green" />{{ __st('cart.mini_free_shipping') }}</p>
            @elseif ($free !== null && ! $free['reached'])
                <p class="flex w-full items-center gap-2 rounded-lg bg-[#ffebeb] p-2 text-sm tracking-[0.5px] text-eco-black"><x-ui::icon name="truck" :size="16" class="shrink-0" />{{ __st('cart.mini_free_more', ['progress' => $free['progress']]) }}</p>
            @endif
            <div class="grid w-full gap-3">
                {{-- A blocked cart keeps a working button: it says why and opens the cart, where each line shows its problem. --}}
                @if ($blocked)
                    <p id="sf-mini-blocked" class="text-center text-xs text-eco-red" role="alert">{{ __st('cart.cannot_checkout') }}</p>
                @endif
                <a href="{{ $blocked ? route('storefront.cart') : route('storefront.checkout') }}" @class(['eco-pill-dark mo-press h-11 w-full rounded-[32px] px-3 text-base font-medium', 'opacity-50' => $blocked]) @if ($blocked) aria-describedby="sf-mini-blocked" data-cart-blocked @endif data-cart-checkout>{{ __st('cart.to_checkout', ['total' => StorefrontCart::price($cart['totals']['grand_total'])]) }}</a>
                <a href="{{ route('storefront.cart') }}" class="mo-press grid h-11 w-full place-items-center rounded-[32px] border border-eco-gray text-base font-medium text-eco-gray hover:border-eco-black hover:text-eco-black">{{ __st('cart.go_to_cart') }}</a>
            </div>
        </div>
        <div class="grid gap-4" data-cart-selection>
            <div class="flex items-center justify-between gap-2">
                <label class="inline-flex items-center gap-4 text-sm font-medium tracking-[0.5px] text-eco-black">
                    <input type="checkbox" class="sf-check size-5" checked data-cart-select-all>
                    <span>{{ __st('cart.select_all') }} ({{ count($lines) }})</span>
                </label>
                <button type="button" class="hidden text-eco-red [[data-has-selection]_&]:inline-flex" data-cart-remove-selected aria-label="{{ __st('cart.remove_selected') }}"><x-ui::icon name="trash" :size="18" /></button>
            </div>
            <ul class="grid gap-4" role="list" data-cart-lines>
                @foreach ($lines as $line)
                    <li class="grid justify-items-center gap-2" data-cart-line="{{ $line['id'] }}" data-quantity="{{ $line['quantity'] }}">
                        <div class="eco-line-thumb !w-[120px]">
                            @if ($line['image_url'])
                                <img src="{{ $line['image_url'] }}" alt="{{ $line['name'] }}" width="120" height="120" loading="lazy" decoding="async">
                            @else
                                <span class="line-clamp-3 text-center text-xs text-eco-gray">{{ $line['name'] }}</span>
                            @endif
                            <input type="checkbox" class="sf-check absolute start-2 top-2 size-5" checked data-cart-select aria-label="{{ __st('cart.select_item', ['name' => $line['name']]) }}">
                        </div>
                        @if ($line['issue'] !== null)
                            <p class="text-center text-xs text-eco-red">{{ $line['message'] }}</p>
                        @else
                            <p class="text-base font-medium text-eco-black">{{ StorefrontCart::price($line['total']) }}</p>
                        @endif
                        <label class="relative inline-flex">
                            <span class="sr-only">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</span>
                            <select class="h-7 w-[78px] cursor-pointer appearance-none rounded border border-eco-gray bg-white ps-2 pe-6 text-base font-medium text-eco-black" data-cart-qty>
                                @for ($quantity = 1; $quantity <= max(10, (int) $line['quantity']); $quantity++)
                                    <option value="{{ $quantity }}" @selected($quantity === (int) $line['quantity'])>{{ $quantity }}</option>
                                @endfor
                            </select>
                            <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-1.5 top-1/2 -translate-y-1/2" />
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
