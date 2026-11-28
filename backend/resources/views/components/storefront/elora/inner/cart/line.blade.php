{{--
    Elora cart line (Figma 1:17541 desktop rows / m 1:22029 phone cards): select box (desktop only),
    image tile, name + weight, variant, estimated delivery (desktop), price + struck subtotal and the
    − n + stepper. Desktop: trash top end, the minus is disabled at 1. Phone: the minus turns into a
    trash at 1, flash tag on the image. Swipe toward the inline-start edge removes it on touch with an
    undo toast (core/motion/swipe-remove.js); the trash does the same for mouse / keyboard. Same hooks
    as x-sf::cart.line (commerce/lines.js). $line = Store API line, $delivery = cart delivery estimate.
--}}
@props(['line', 'delivery' => null])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@use('App\Modules\Storefront\Services\Catalog\ProductCards')
@php
    $url = route('storefront.product', ['product' => $line['store_product_id']]);
    $discounted = StorefrontCart::positive($line['discount']);
    $issue = $line['issue'] !== null;
    $qty = (int) $line['quantity'];
    $weight = ($line['weight_grams'] ?? 0) > 0 ? ProductCards::weight((int) $line['weight_grams']) : null;
    $eta = $delivery ? __st('checkout.estimated_delivery', ['from' => \Illuminate\Support\Carbon::parse($delivery['min_date'])->translatedFormat('j F'), 'to' => \Illuminate\Support\Carbon::parse($delivery['max_date'])->translatedFormat('j F')]) : null;
    $removeLabel = __st('cart.remove_item', ['name' => $line['name']]);
@endphp
<li class="relative overflow-hidden" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-product-id="{{ $line['store_product_id'] }}" data-variant-id="{{ $line['variant_id'] }}" data-quantity="{{ $qty }}">
    <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="22" /></div>
    <div class="ecl" data-swipe-content>
        <x-sf::elora.check wrap="ecl-check" checked data-cart-select aria-label="{{ __st('cart.select_item', ['name' => $line['name']]) }}" />
        <a href="{{ $url }}" class="ecl-img" tabindex="-1">
            @if ($line['image_url'])
                <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain" width="171" height="162" loading="lazy" decoding="async">
            @else
                <x-ui::icon name="image" :size="28" class="text-elo-line" />
            @endif
            @if ($line['flash_sale'])
                <span class="ecl-tag">{{ __st('cart.off', ['percent' => $line['flash_sale']['percentage']]) }}</span>
            @endif
        </a>
        <div class="ecl-main">
            <div class="ecl-top">
                <div class="ecl-info">
                    <div class="ecl-title">
                        <a href="{{ $url }}" class="ecl-name" dir="auto" title="{{ $line['name'] }}">{{ $line['name'] }}</a>
                        @if ($weight)<span class="ecl-weight" dir="ltr">{{ $weight }}</span>@endif
                    </div>
                    @if ($line['variant'])
                        <p class="ecl-variant" dir="auto">{{ $line['variant'] }}</p>
                    @endif
                    @if ($eta)
                        <p class="ecl-eta">{{ $eta }}</p>
                    @endif
                </div>
                <button type="button" class="ecl-trash mo-press" data-swipe-remove-button aria-label="{{ $removeLabel }}"><x-ui::icon name="elo-trash" :size="20" /></button>
            </div>
            <div class="ecl-bottom">
                @if ($issue)
                    <p class="flex items-center gap-1.5 text-xs text-sf-danger" role="status"><x-ui::icon name="info" :size="14" class="shrink-0" />{{ $line['message'] }}</p>
                @else
                    <p class="ecl-prices">
                        <span class="ecl-price">{{ StorefrontCart::price($line['total']) }}</span>
                        @if ($discounted)
                            <del class="ecl-was">{{ StorefrontCart::price($line['subtotal']) }}</del>
                        @endif
                    </p>
                @endif
                <div class="elo-stepper" data-elo-stepper>
                    @if ($qty <= 1)
                        <button type="button" class="ecl-step-trash lg:hidden" data-swipe-remove-button aria-label="{{ $removeLabel }}"><x-ui::icon name="elo-trash-bin" :size="18" /></button>
                    @endif
                    <button type="button" @class(['max-lg:hidden' => $qty <= 1]) data-elo-step="-1" aria-label="{{ __st('elora_pages.cart.decrease') }}" @disabled($qty <= 1)><x-ui::icon name="elo-minus" :size="18" class="max-lg:hidden" /><x-ui::icon name="elo-round-minus" :size="18" class="lg:hidden" /></button>
                    <label class="sr-only" for="elo-qty-{{ $line['id'] }}">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</label>
                    <input id="elo-qty-{{ $line['id'] }}" type="number" inputmode="numeric" min="1" max="99" value="{{ $qty }}" readonly data-cart-qty>
                    <button type="button" data-elo-step="1" aria-label="{{ __st('elora_pages.cart.increase') }}" @disabled($qty >= 99)><x-ui::icon name="elo-plus" :size="18" class="max-lg:hidden" /><x-ui::icon name="elo-round-plus" :size="18" class="lg:hidden" /></button>
                </div>
            </div>
        </div>
    </div>
</li>
