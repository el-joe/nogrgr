{{--
    Cart line (Ecommet cart 1:1363, m 1:3210): select checkbox, image tile with the flash
    "-30%off" tag, name, variant, trash, price + compare price, "Qty n" select. Rows swipe
    toward the inline-start edge to remove (touch) with an undo toast; the trash button does
    the same for mouse / keyboard (core/motion/swipe-remove.js). Unavailable lines show the
    issue message (out of stock, not sold in this country…) instead of the price.
    $line = one Store API cart line (CartResource::line).
--}}
@props(['line'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $url = route('storefront.product', ['product' => $line['store_product_id']]);
    $discounted = StorefrontCart::positive($line['discount']);
    /* Qty list: up to min(real stock, 10 per order); a line already above it keeps its own quantity so the select stays valid. */
    $stockCap = $line['available'] !== null ? max(1, (int) $line['available']) : 10;
    $max = max(min(10, $stockCap), (int) $line['quantity']);
    $issue = $line['issue'] !== null;
@endphp
<li class="relative" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-product-id="{{ $line['store_product_id'] }}" data-variant-id="{{ $line['variant_id'] }}" data-quantity="{{ $line['quantity'] }}">
    <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="22" /></div>
    <div class="flex items-center gap-3 border-b border-eco-semi bg-white py-4 lg:gap-4 lg:py-5" data-swipe-content>
        <input type="checkbox" class="sf-check size-5 shrink-0 lg:size-6" checked data-cart-select aria-label="{{ __st('cart.select_item', ['name' => $line['name']]) }}">
        <div class="flex min-w-0 flex-1 items-start gap-3 lg:gap-4">
            <a href="{{ $url }}" class="eco-line-thumb" tabindex="-1">
                @if ($line['image_url'])
                    <img src="{{ $line['image_url'] }}" alt="" width="140" height="140" loading="lazy" decoding="async">
                @else
                    <x-ui::icon name="image" :size="28" class="text-eco-semi" />
                @endif
                @if ($line['flash_sale'])
                    <span class="absolute start-0 top-0 bg-eco-red p-0.5 text-[10px] tracking-[0.5px] text-white lg:p-1 lg:text-xs">{{ __st('cart.off', ['percent' => $line['flash_sale']['percentage']]) }}</span>
                @endif
            </a>
            <div class="flex min-w-0 flex-1 flex-col justify-between gap-2 self-stretch lg:py-1">
                <div class="flex items-start justify-between gap-3">
                    <div class="grid min-w-0 gap-1 tracking-[0.5px] lg:gap-2">
                        <a href="{{ $url }}" class="line-clamp-2 text-sm text-eco-black hover:underline lg:line-clamp-1 lg:text-xs">{{ $line['name'] }}</a>
                        @if ($line['variant'])
                            <p class="truncate text-xs text-eco-gray">{{ $line['variant'] }}</p>
                        @endif
                    </div>
                    <button type="button" class="mo-press -m-1.5 shrink-0 p-1.5 text-eco-black hover:text-eco-red" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}">
                        <x-ui::icon name="trash" :size="20" />
                    </button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    @if ($issue)
                        <p class="flex items-center gap-1.5 text-xs text-eco-red" role="status"><x-ui::icon name="info" :size="14" class="shrink-0" />{{ $line['message'] }}</p>
                    @else
                        <p class="flex items-center gap-2">
                            <span class="text-base text-eco-black">{{ StorefrontCart::price($line['total']) }}</span>
                            @if ($discounted)
                                <del class="text-sm tracking-[0.5px] text-eco-gray">{{ StorefrontCart::price($line['subtotal']) }}</del>
                            @endif
                        </p>
                    @endif
                    <label class="relative inline-flex">
                        <span class="sr-only">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</span>
                        <select class="h-6 w-[72px] cursor-pointer appearance-none rounded border border-eco-black bg-white ps-2 pe-6 text-xs tracking-[0.5px] text-eco-black lg:w-[78px]" data-cart-qty>
                            @for ($quantity = 1; $quantity <= $max; $quantity++)
                                <option value="{{ $quantity }}" @selected($quantity === (int) $line['quantity'])>{{ __st('cart.qty', ['qty' => $quantity]) }}</option>
                            @endfor
                        </select>
                        <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-1.5 top-1/2 -translate-y-1/2" />
                    </label>
                </div>
            </div>
        </div>
    </div>
</li>
