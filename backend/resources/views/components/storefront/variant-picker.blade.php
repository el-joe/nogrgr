{{--
    Ecommet variant picker (old Ecommet "choose variant" modal): opened by a card cart
    button with [data-variant-picker]; storefront/ecommet/variant-picker.js loads the
    product from the Store API (products.show), renders the option groups with the
    product page's chips / swatches, updates image, price, compare, discount and stock per
    variant, then adds the chosen variant + quantity through catalog/cart.js
    ([data-add-to-cart] inside [data-cart-scope]). Bottom sheet on phones (x-sf::modal).
--}}
@props(['theme' => 'ecommet'])
@php
    $elora = $theme === 'elora';
    /* Class map: Ecommet keeps its eco-* look; Elora uses the v1-vp-* skin (css/storefront/elora-picker.css). */
    $c = $elora ? [
        'skeleton' => 'v1-vp-skeleton', 'red' => 'v1-vp-red', 'dark' => 'v1-vp-cta', 'outline' => 'v1-vp-outline', 'media' => 'v1-vp-media',
        'img' => 'size-full object-contain', 'name' => 'v1-vp-name', 'compare' => 'v1-vp-compare', 'price' => 'v1-vp-price', 'discount' => 'v1-vp-discount',
        'stock' => 'v1-vp-stock', 'muted' => 'v1-vp-muted', 'row' => 'v1-vp-row', 'select' => 'v1-vp-select', 'add' => 'v1-vp-cta v1-vp-add', 'legend' => 'v1-vp-legend', 'more' => 'v1-vp-more', 'err' => 'v1-vp-err-icon',
    ] : [
        'skeleton' => 'eco-skeleton', 'red' => 'text-eco-red', 'dark' => 'eco-pill-dark', 'outline' => 'inline-flex h-9 items-center rounded-full border border-eco-black px-5 text-sm text-eco-black', 'media' => 'grid aspect-square place-items-center overflow-hidden rounded-lg bg-eco-img p-2',
        'img' => 'size-full object-contain mix-blend-multiply', 'name' => 'line-clamp-2 text-sm tracking-[0.5px] text-eco-black hover:underline', 'compare' => 'eco-compare', 'price' => 'text-lg font-medium text-eco-black', 'discount' => 'eco-discount',
        'stock' => 'text-xs tracking-[0.5px]', 'muted' => 'text-sm text-eco-gray', 'row' => 'flex flex-wrap items-center gap-3 border-t border-eco-stroke pt-4', 'select' => 'h-[34px] w-[72px] rounded border border-eco-black bg-white px-2 text-base', 'add' => 'eco-pill-dark relative h-10 min-w-[180px] flex-1 font-medium disabled:cursor-not-allowed disabled:bg-eco-semi', 'legend' => 'mb-2 text-sm text-eco-black', 'more' => 'justify-self-center text-sm text-eco-black underline-offset-4 hover:underline', 'err' => 'text-eco-red',
    ];
    $skin = $elora ? ['swatch' => 'v1-vp-swatch', 'option' => 'v1-vp-option', 'tiles' => 'v1-vp-tiles', 'tile' => 'v1-vp-tile', 'tileText' => 'v1-vp-tile-text', 'red' => 'v1-vp-stock-red', 'green' => 'v1-vp-stock-green', 'gray' => 'v1-vp-stock-gray'] : null;
@endphp
<x-sf::modal :id="$elora ? 'sf-variant-modal' : 'eco-variant-modal'" :title="__st('catalog.variant.title')" size="md" data-variant-modal :data-variant-skin="$skin ? json_encode($skin) : null">
    <div class="grid gap-5" data-variant-body data-cart-scope data-product aria-live="polite">
        {{-- Loading --}}
        <div class="grid grid-cols-[112px_1fr] gap-4" data-variant-state="loading">
            <span class="{{ $c['skeleton'] }} aspect-square rounded-lg"></span>
            <span class="grid content-start gap-2">
                <span class="{{ $c['skeleton'] }} h-4 w-4/5 rounded"></span>
                <span class="{{ $c['skeleton'] }} h-5 w-1/3 rounded"></span>
                <span class="{{ $c['skeleton'] }} h-3 w-1/4 rounded"></span>
            </span>
            <span class="sr-only">{{ __st('catalog.variant.loading') }}</span>
        </div>

        {{-- Error --}}
        <div class="grid justify-items-center gap-3 py-6 text-center" data-variant-state="error" hidden>
            <x-ui::icon name="alert-triangle" :size="36" class="{{ $c['err'] }}" />
            <p class="{{ $c['muted'] }}">{{ __st('catalog.variant.error') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" class="{{ $c['dark'] }} h-9 px-5 text-sm" data-variant-retry>{{ __st('catalog.variant.retry') }}</button>
                <a href="{{ route('storefront.categories.index') }}" class="{{ $c['outline'] }}" data-variant-details>{{ __st('catalog.variant.details') }}</a>
            </div>
        </div>

        {{-- Ready --}}
        <div class="grid gap-5" data-variant-state="ready" hidden>
            <div class="grid grid-cols-[112px_1fr] items-start gap-4">
                <span class="{{ $c['media'] }}">
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="" width="112" height="112" class="{{ $c['img'] }}" data-variant-image data-fly-image>
                </span>
                <div class="grid min-w-0 content-start gap-2">
                    <a href="{{ route('storefront.categories.index') }}" class="{{ $c['name'] }}" data-variant-name data-variant-details></a>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 whitespace-nowrap">
                        <span class="{{ $c['compare'] }}" data-variant-compare hidden></span>
                        <span class="{{ $c['price'] }}" data-variant-price></span>
                        <span class="{{ $c['discount'] }}" data-variant-discount hidden></span>
                    </p>
                    <p class="{{ $c['stock'] }}" data-variant-stock></p>
                </div>
            </div>

            <div class="grid max-h-[40vh] gap-4 overflow-y-auto overscroll-contain" data-variant-attributes></div>

            <div class="{{ $c['row'] }}">
                <label for="{{ $elora ? 'sf' : 'eco' }}-variant-qty" class="{{ $elora ? 'v1-vp-label' : 'text-sm text-eco-black' }}">{{ __st('product.qty') }}</label>
                <select id="{{ $elora ? 'sf' : 'eco' }}-variant-qty" class="{{ $c['select'] }}" data-qty>
                    <option value="1">1</option>
                </select>
                <button type="button" class="{{ $c['add'] }}" data-add-to-cart data-product-id="" data-variant-id="" data-state="idle" disabled>
                    <span data-cart-label>{{ __st('product.add_to_cart') }}</span>
                </button>
            </div>
            <a href="{{ route('storefront.categories.index') }}" class="{{ $c['more'] }}" data-variant-details>{{ __st('catalog.variant.details') }}</a>
        </div>
    </div>
    <template data-variant-group>
        <fieldset class="grid gap-2">
            <legend class="{{ $c['legend'] }}"><span data-group-name></span>: <span class="{{ $elora ? 'v1-vp-muted' : 'text-eco-gray' }}" data-group-value></span></legend>
            <div class="flex flex-wrap gap-2" role="radiogroup" data-group-options></div>
        </fieldset>
    </template>
    <script type="application/json" data-variant-texts>{!! json_encode([
        'add' => __st('product.add_to_cart'),
        'out' => __st('product.out_of_stock'),
        'unavailable' => __st('product.unavailable'),
        'choose' => __st('catalog.variant.choose'),
        'in_stock' => __st('catalog.variant.in_stock'),
        'left' => __st('catalog.variant.left', ['count' => '__COUNT__']),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</x-sf::modal>
