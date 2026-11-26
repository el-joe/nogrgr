{{--
    Souqify mini cart content (Storefront additions 137:112371 / m(ar) 137:112504): "Added to your
    cart" note (shown when the drawer opens after an add), item rows (image, name, variant ·
    qty, total, swipe / trash to remove), free-shipping hint + meter, "Subtotal (n items)",
    Checkout and View cart. Rendered by `storefront.cart.mini`; empty → short empty state.
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
        <div class="sq-empty py-10">
            <span class="sq-empty-icon size-20"><x-ui::icon name="cart" :size="36" /></span>
            <h2>{{ __st('souqify_pages.cart.empty_title') }}</h2>
            <p>{{ __st('souqify_pages.cart.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="sq-btn sq-btn-primary mo-press mt-2 w-full">{{ __st('souqify_pages.cart.continue') }}</a>
        </div>
    @else
        <p class="sq-added hidden [[data-added]_&]:flex" role="status"><x-ui::icon name="check-circle" :size="18" />{{ __st('souqify_pages.cart.added') }}</p>
        <ul class="grid gap-3" role="list" data-cart-lines>
            @foreach ($lines as $line)
                <li class="relative overflow-hidden" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-quantity="{{ $line['quantity'] }}">
                    <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="20" /></div>
                    <div class="flex items-center gap-3 bg-white" data-swipe-content>
                        <a href="{{ route('storefront.product', ['product' => $line['store_product_id']]) }}" class="grid size-[54px] shrink-0 place-items-center overflow-hidden rounded bg-sq-soft">
                            @if ($line['image_url'])
                                <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain bg-media" loading="lazy" decoding="async">
                            @else
                                <x-ui::icon name="image" :size="20" class="text-sq-line" />
                            @endif
                        </a>
                        <div class="grid min-w-0 flex-1 gap-0.5">
                            <p class="truncate text-sm text-sq-ink">{{ $line['name'] }}</p>
                            <p class="truncate text-xs text-sf-muted">{{ collect([$line['variant'], __st('cart.qty', ['qty' => $line['quantity']])])->filter()->implode(' · ') }}</p>
                            @if ($line['issue'] !== null)
                                <p class="text-xs text-sf-danger">{{ $line['message'] }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 text-sm font-medium text-sq-ink">{{ StorefrontCart::price($line['total']) }}</p>
                        <button type="button" class="mo-press shrink-0 p-1 text-sf-muted hover:text-sf-danger" data-swipe-remove-button aria-label="{{ __st('cart.remove_item', ['name' => $line['name']]) }}"><x-ui::icon name="trash" :size="16" /></button>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($free !== null && ! $free['reached'])
            @php($weight = $free['remaining_grams'] >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($free['remaining_grams'] / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => (int) $free['remaining_grams']]))
            <div class="grid gap-2">
                <p class="text-[11px] text-sf-muted">{{ __st('souqify_pages.cart.add_more_free', ['weight' => $weight]) }}</p>
                <div class="sq-meter h-1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $free['progress'] }}" aria-label="{{ __st('cart.free_shipping_progress') }}"><span data-width="{{ $free['progress'] }}"></span></div>
            </div>
        @elseif ($free !== null)
            <p class="text-xs text-sq-green">{{ __st('cart.free_shipping_reached') }}</p>
        @endif
        <p class="flex items-center justify-between gap-3 text-sm text-sq-ink">{{ __st('souqify_pages.cart.subtotal_items', ['count' => $cart['items_count']]) }}<span class="text-lg font-medium">{{ StorefrontCart::price($cart['totals']['items_total']) }}</span></p>
        <div class="grid gap-3">
            <a href="{{ route('storefront.checkout') }}" @class(['sq-btn sq-btn-primary mo-press h-12 w-full rounded-full', 'pointer-events-none opacity-50' => $blocked]) @if ($blocked) aria-disabled="true" tabindex="-1" @endif data-cart-checkout>{{ __st('cart.checkout') }}</a>
            <a href="{{ route('storefront.cart') }}" class="sq-btn sq-btn-outline mo-press h-12 w-full rounded-full">{{ __st('souqify_pages.cart.view_cart') }}</a>
        </div>
    @endif
</div>
