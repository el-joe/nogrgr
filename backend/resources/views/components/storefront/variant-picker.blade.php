{{--
    Ecommet variant picker (old Ecommet "choose variant" modal): opened by a card cart
    button with [data-variant-picker]; storefront/ecommet/variant-picker.js loads the
    product from the Store API (products.show), renders the option groups with the
    product page's chips / swatches, updates image, price, compare, discount and stock per
    variant, then adds the chosen variant + quantity through catalog/cart.js
    ([data-add-to-cart] inside [data-cart-scope]). Bottom sheet on phones (x-sf::modal).
--}}
<x-sf::modal id="eco-variant-modal" :title="__st('catalog.variant.title')" size="md">
    <div class="grid gap-5" data-variant-body data-cart-scope data-product aria-live="polite">
        {{-- Loading --}}
        <div class="grid grid-cols-[112px_1fr] gap-4" data-variant-state="loading">
            <span class="eco-skeleton aspect-square rounded-lg"></span>
            <span class="grid content-start gap-2">
                <span class="eco-skeleton h-4 w-4/5 rounded"></span>
                <span class="eco-skeleton h-5 w-1/3 rounded"></span>
                <span class="eco-skeleton h-3 w-1/4 rounded"></span>
            </span>
            <span class="sr-only">{{ __st('catalog.variant.loading') }}</span>
        </div>

        {{-- Error --}}
        <div class="grid justify-items-center gap-3 py-6 text-center" data-variant-state="error" hidden>
            <x-ui::icon name="alert-triangle" :size="36" class="text-eco-red" />
            <p class="text-sm text-eco-gray">{{ __st('catalog.variant.error') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" class="eco-pill-dark h-9 px-5 text-sm" data-variant-retry>{{ __st('catalog.variant.retry') }}</button>
                <a href="{{ route('storefront.categories.index') }}" class="inline-flex h-9 items-center rounded-full border border-eco-black px-5 text-sm text-eco-black" data-variant-details>{{ __st('catalog.variant.details') }}</a>
            </div>
        </div>

        {{-- Ready --}}
        <div class="grid gap-5" data-variant-state="ready" hidden>
            <div class="grid grid-cols-[112px_1fr] items-start gap-4">
                <span class="grid aspect-square place-items-center overflow-hidden rounded-lg bg-eco-img p-2">
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="" width="112" height="112" class="size-full object-contain mix-blend-multiply" data-variant-image data-fly-image>
                </span>
                <div class="grid min-w-0 content-start gap-2">
                    <a href="{{ route('storefront.categories.index') }}" class="line-clamp-2 text-sm tracking-[0.5px] text-eco-black hover:underline" data-variant-name data-variant-details></a>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 whitespace-nowrap">
                        <span class="eco-compare" data-variant-compare hidden></span>
                        <span class="text-lg font-medium text-eco-black" data-variant-price></span>
                        <span class="eco-discount" data-variant-discount hidden></span>
                    </p>
                    <p class="text-xs tracking-[0.5px]" data-variant-stock></p>
                </div>
            </div>

            <div class="grid max-h-[40vh] gap-4 overflow-y-auto overscroll-contain" data-variant-attributes></div>

            <div class="flex flex-wrap items-center gap-3 border-t border-eco-stroke pt-4">
                <label for="eco-variant-qty" class="text-sm text-eco-black">{{ __st('product.qty') }}</label>
                <select id="eco-variant-qty" class="h-[34px] w-[72px] rounded border border-eco-black bg-white px-2 text-base" data-qty>
                    <option value="1">1</option>
                </select>
                <button type="button" class="eco-pill-dark relative h-10 min-w-[180px] flex-1 font-medium disabled:cursor-not-allowed disabled:bg-eco-semi" data-add-to-cart data-product-id="" data-variant-id="" data-state="idle" disabled>
                    <span data-cart-label>{{ __st('product.add_to_cart') }}</span>
                </button>
            </div>
            <a href="{{ route('storefront.categories.index') }}" class="justify-self-center text-sm text-eco-black underline-offset-4 hover:underline" data-variant-details>{{ __st('catalog.variant.details') }}</a>
        </div>
    </div>
    <template data-variant-group>
        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm text-eco-black"><span data-group-name></span>: <span class="text-eco-gray" data-group-value></span></legend>
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
