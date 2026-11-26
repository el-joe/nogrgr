{{--
    Souqify cart line (Figma 1:75039 / m 1:76392): white card — image tile (flash "-n% off"
    tag), name, variant, minimal − n + stepper (minus turns into trash at 1), line total in
    the accent colour with the struck subtotal, "Remove". Swipe toward the inline-start edge
    removes it on touch with an undo toast (core/motion/swipe-remove.js); the Remove button
    does the same for mouse / keyboard. Same hooks as x-sf::cart.line (commerce/lines.js).
--}}
@props(['line'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $url = route('storefront.product', ['product' => $line['store_product_id']]);
    $discounted = StorefrontCart::positive($line['discount']);
    $issue = $line['issue'] !== null;
    $qty = (int) $line['quantity'];
@endphp
<li class="relative overflow-hidden rounded-xl" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-product-id="{{ $line['store_product_id'] }}" data-variant-id="{{ $line['variant_id'] }}" data-quantity="{{ $qty }}">
    <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="22" /></div>
    <div class="flex gap-3 rounded-xl bg-white p-3 lg:gap-6 lg:p-5" data-swipe-content>
        <a href="{{ $url }}" class="relative grid size-[96px] shrink-0 place-items-center overflow-hidden rounded-lg bg-sq-soft lg:size-[148px]" tabindex="-1">
            @if ($line['image_url'])
                <img src="{{ $line['image_url'] }}" alt="" class="size-full object-cover" loading="lazy" decoding="async">
            @else
                <x-ui::icon name="image" :size="28" class="text-sq-line" />
            @endif
            @if ($line['flash_sale'])
                <span class="absolute start-0 top-0 rounded-ee-md bg-sq-orange px-1.5 py-0.5 text-[10px] text-white">{{ __st('cart.off', ['percent' => $line['flash_sale']['percentage']]) }}</span>
            @endif
        </a>
        <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex items-start justify-between gap-3">
                <div class="grid min-w-0 gap-1">
                    <a href="{{ $url }}" class="line-clamp-2 text-sm text-sq-ink hover:underline lg:text-lg">{{ $line['name'] }}</a>
                    @if ($line['variant'])
                        <p class="truncate text-xs text-sf-muted">{{ $line['variant'] }}</p>
                    @endif
                </div>
                @unless ($issue)
                    <p class="grid shrink-0 justify-items-end text-end">
                        <span class="text-base font-medium text-sq-price lg:text-xl">{{ StorefrontCart::price($line['total']) }}</span>
                        @if ($discounted)
                            <del class="text-xs text-sf-muted">{{ StorefrontCart::price($line['subtotal']) }}</del>
                        @endif
                    </p>
                @endunless
            </div>
            @if ($issue)
                <p class="flex items-center gap-1.5 text-xs text-sf-danger" role="status"><x-ui::icon name="info" :size="14" class="shrink-0" />{{ $line['message'] }}</p>
            @endif
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2">
                <div class="sq-stepper" data-sq-stepper>
                    @if ($qty <= 1)
                        <button type="button" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}"><x-ui::icon name="trash" :size="16" /></button>
                    @else
                        <button type="button" data-sq-step="-1" aria-label="{{ __st('souqify_pages.cart.decrease') }}"><x-ui::icon name="minus" :size="18" /></button>
                    @endif
                    <label class="sr-only" for="sq-qty-{{ $line['id'] }}">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</label>
                    <input id="sq-qty-{{ $line['id'] }}" type="number" inputmode="numeric" min="1" max="99" value="{{ $qty }}" readonly data-cart-qty>
                    <button type="button" data-sq-step="1" aria-label="{{ __st('souqify_pages.cart.increase') }}" @disabled($qty >= 99)><x-ui::icon name="plus" :size="18" /></button>
                </div>
                <button type="button" class="mo-press inline-flex items-center gap-1.5 text-xs text-sf-muted hover:text-sf-danger" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}"><x-ui::icon name="trash" :size="14" />{{ __st('souqify_pages.cart.remove') }}</button>
            </div>
        </div>
    </div>
</li>
