{{--
    Elora mini cart (Storefront additions 137:112132 desktop side panel / m 137:112323
    bottom sheet), rendered once per page by the header cart entry. The header cart opens
    it; after "Add to cart" it opens on its own with the "Added to your cart" note. Filled
    from `storefront.cart.mini` by resources/js/storefront/elora/pages/mini-cart.js; drag /
    swipe toward its edge (or down on phones) to close.
--}}
<div class="fixed inset-0 z-[60]" id="elo-mini-cart" role="dialog" aria-modal="true" aria-labelledby="elo-mini-cart-title" data-elo-mini-cart data-url="{{ route('storefront.cart.mini') }}" hidden>
    <div class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300 [[data-open]_&]:opacity-100" data-elo-mini-close></div>
    <aside class="elo-drawer-panel" data-elo-mini-panel>
        <span class="mx-auto mt-2 h-1 w-10 rounded-full bg-elo-line lg:hidden" aria-hidden="true"></span>
        <div class="flex items-center justify-between gap-3 px-5 pb-3 pt-4">
            <h2 id="elo-mini-cart-title" class="text-xl font-medium text-elo-ink">{{ __st('elora_pages.cart.your_cart') }} (<span data-cart-count>0</span>)</h2>
            <button type="button" class="mo-press text-sf-muted hover:text-elo-ink" data-elo-mini-close aria-label="{{ __st('cart.close') }}"><x-ui::icon name="x" :size="22" /></button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-[calc(20px+env(safe-area-inset-bottom))]" data-elo-mini-body aria-live="polite">
            <div class="grid gap-3" aria-hidden="true">
                <span class="eco-skeleton h-10"></span>
                <span class="eco-skeleton h-[54px]"></span>
                <span class="eco-skeleton h-[54px]"></span>
                <span class="eco-skeleton h-12 rounded-full"></span>
            </div>
        </div>
    </aside>
</div>
