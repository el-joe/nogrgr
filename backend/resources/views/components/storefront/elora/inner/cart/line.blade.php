{{--
    Elora cart line (Figma 1:17541 / m 1:22029): select box, image tile with the flash
    "-30%off" tag, name, variant, price + struck subtotal, trash and the − n + stepper
    (minus turns into trash at 1). Swipe toward the inline-start edge removes it on touch
    with an undo toast (core/motion/swipe-remove.js); the trash does the same for mouse /
    keyboard. Same hooks as x-sf::cart.line (commerce/lines.js). $line = Store API line.
--}}
@props(['line'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $url = route('storefront.product', ['product' => $line['store_product_id']]);
    $discounted = StorefrontCart::positive($line['discount']);
    $issue = $line['issue'] !== null;
    $qty = (int) $line['quantity'];
@endphp
<li class="relative overflow-hidden" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-product-id="{{ $line['store_product_id'] }}" data-variant-id="{{ $line['variant_id'] }}" data-quantity="{{ $qty }}">
    <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="22" /></div>
    <div class="flex items-center gap-3 border-b border-elo-line bg-white py-4 lg:gap-5" data-swipe-content>
        <input type="checkbox" class="sf-check size-5 shrink-0 accent-[var(--nog-ink)]" checked data-cart-select aria-label="{{ __st('cart.select_item', ['name' => $line['name']]) }}">
        <a href="{{ $url }}" class="relative grid size-[84px] shrink-0 place-items-center overflow-hidden rounded-md bg-elo-soft lg:h-[162px] lg:w-[170px]" tabindex="-1">
            @if ($line['image_url'])
                <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain bg-media" loading="lazy" decoding="async">
            @else
                <x-ui::icon name="image" :size="28" class="text-elo-line" />
            @endif
            @if ($line['flash_sale'])
                <span class="absolute end-0 top-0 rounded-bl bg-[#ffc107] px-1 text-[9px] text-elo-ink">{{ __st('cart.off', ['percent' => $line['flash_sale']['percentage']]) }}</span>
            @endif
        </a>
        <div class="flex min-w-0 flex-1 flex-col gap-1.5 self-stretch">
            <div class="flex items-start justify-between gap-3">
                <div class="grid min-w-0 gap-1">
                    <a href="{{ $url }}" class="line-clamp-2 text-sm text-elo-ink hover:underline">{{ $line['name'] }}</a>
                    @if ($line['variant'])
                        <p class="truncate text-xs text-sf-muted">{{ $line['variant'] }}</p>
                    @endif
                </div>
                <button type="button" class="mo-press -m-1.5 shrink-0 p-1.5 text-sf-muted hover:text-sf-danger" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}"><x-ui::icon name="trash" :size="18" /></button>
            </div>
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2">
                @if ($issue)
                    <p class="flex items-center gap-1.5 text-xs text-sf-danger" role="status"><x-ui::icon name="info" :size="14" class="shrink-0" />{{ $line['message'] }}</p>
                @else
                    <p class="flex items-baseline gap-2">
                        <span class="text-base font-medium text-elo-ink">{{ StorefrontCart::price($line['total']) }}</span>
                        @if ($discounted)
                            <del class="text-xs text-sf-muted">{{ StorefrontCart::price($line['subtotal']) }}</del>
                        @endif
                    </p>
                @endif
                <div class="elo-stepper" data-elo-stepper>
                    @if ($qty <= 1)
                        <button type="button" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}"><x-ui::icon name="trash" :size="14" /></button>
                    @else
                        <button type="button" data-elo-step="-1" aria-label="{{ __st('elora_pages.cart.decrease') }}"><x-ui::icon name="minus" :size="14" /></button>
                    @endif
                    <label class="sr-only" for="elo-qty-{{ $line['id'] }}">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</label>
                    <input id="elo-qty-{{ $line['id'] }}" type="number" inputmode="numeric" min="1" max="99" value="{{ $qty }}" readonly data-cart-qty>
                    <button type="button" data-elo-step="1" aria-label="{{ __st('elora_pages.cart.increase') }}" @disabled($qty >= 99)><x-ui::icon name="plus" :size="14" /></button>
                </div>
            </div>
        </div>
    </div>
</li>
