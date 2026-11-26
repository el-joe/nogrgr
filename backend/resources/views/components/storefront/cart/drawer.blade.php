{{--
    Header mini cart drawer (Ecommet 1:1662, 220 px side panel) + the "Item added to cart"
    dialog (1:4267). Rendered once per page by the header cart entry; filled from
    `storefront.cart.mini` by resources/js/storefront/commerce/mini-cart.js. The drawer
    slides in from the inline-end edge and can be dragged / swiped back to close.
--}}
<div class="fixed inset-0 z-[60]" id="sf-mini-cart" role="dialog" aria-modal="true" aria-label="{{ __st('cart.title') }}" data-mini-cart data-url="{{ route('storefront.cart.mini') }}" hidden>
    <div class="absolute inset-0 bg-black/30 opacity-0 transition-opacity duration-300 [[data-open]_&]:opacity-100" data-mini-cart-close></div>
    <aside class="absolute inset-y-0 end-0 flex w-[260px] max-w-[85vw] flex-col bg-white shadow-[-4px_0_4px_rgba(178,178,178,0.25)] transition-transform duration-300 ease-[var(--ease-out)] ltr:translate-x-full rtl:-translate-x-full [[data-open]_&]:translate-x-0 motion-reduce:transition-none" data-mini-cart-panel>
        <div class="flex justify-end p-4 pb-0">
            <button type="button" class="mo-press text-eco-black" data-mini-cart-close aria-label="{{ __st('cart.close') }}"><x-ui::icon name="x" :size="24" /></button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-6 pt-2" data-mini-cart-body aria-live="polite">
            <div class="grid gap-4" aria-hidden="true">
                <div class="mo-skeleton mx-auto h-12 w-24 rounded"></div>
                <div class="mo-skeleton h-11 rounded-[32px]"></div>
                <div class="mo-skeleton h-11 rounded-[32px]"></div>
                <div class="mo-skeleton mx-auto h-[106px] w-[139px]"></div>
            </div>
        </div>
    </aside>
</div>

<div class="fixed inset-0 z-[70] grid place-items-center p-4" id="sf-cart-added" role="dialog" aria-modal="true" aria-labelledby="sf-cart-added-title" data-cart-added hidden>
    <div class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-200 [[data-open]_&]:opacity-100" data-cart-added-close></div>
    <div class="relative w-full max-w-[384px] scale-95 rounded-2xl bg-white px-6 py-8 opacity-0 shadow-xl transition duration-200 ease-[var(--ease-out)] [[data-open]_&]:scale-100 [[data-open]_&]:opacity-100 motion-reduce:transition-none" data-cart-added-panel>
        <div class="grid justify-items-center gap-4 text-center">
            <span class="grid size-16 place-items-center rounded-full bg-[#dcfce7] text-[#16a34a]"><x-ui::icon name="check-circle" :size="40" /></span>
            <div class="grid gap-2">
                <h2 id="sf-cart-added-title" class="text-lg font-medium text-eco-black">{{ __st('cart.added.title') }}</h2>
                <p class="text-sm tracking-[0.5px] text-eco-gray" data-cart-added-text data-template="{{ __st('cart.added.body', ['count' => '__COUNT__']) }}"></p>
            </div>
            <p class="flex w-full items-center gap-2 rounded-[10px] bg-[#f0fdf4] px-2.5 py-2 text-sm text-eco-black">
                <x-ui::icon name="check-circle" :size="16" class="shrink-0 text-[#16a34a]" />
                <span class="min-w-0 truncate text-start" data-cart-added-name></span>
                <span class="shrink-0 text-eco-gray" data-cart-added-qty data-template="{{ __st('cart.added.qty', ['qty' => '__QTY__']) }}"></span>
            </p>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-2">
            <button type="button" class="mo-press h-10 rounded-[10px] border border-[#d1d5dc] text-base text-eco-black hover:bg-[#f6f6f6]" data-cart-added-close>{{ __st('cart.close') }}</button>
            <a href="{{ route('storefront.cart') }}" class="mo-press inline-flex h-10 items-center justify-center gap-2 rounded-[10px] bg-eco-black text-base text-white hover:bg-black"><x-ui::icon name="cart" :size="20" />{{ __st('cart.view_cart') }}</a>
        </div>
    </div>
</div>
