{{--
    Souqify cart (Figma 1:75039 / m 1:76392; empty 137:112552 / m(ar) 137:112713): free-shipping
    bar across both columns, line cards + "Order Summary" card; then the orange "Add some items
    to “Get Free Shipping”" band (while the threshold is not reached) and the "Recommended For
    You" rail. Empty cart: the empty state + a row of best sellers.
    Inputs as the Ecommet cart: $cart (Store API cart payload), $picks (best sellers),
    $picksMoreUrl, $seo. Body and summary are swapped in by commerce/cart-page.js after each
    Store API mutation.
--}}
@php
    $free = $cart['free_shipping'];
    $empty = $cart['lines'] === [];
    $cards = $picks->cards;
    $band = ! $empty && $free !== null && ! $free['reached'] ? array_slice($cards, 0, 3) : [];
    $rail = $empty ? [] : array_slice($cards, count($band));
@endphp
<x-sf::layout :seo="$seo" :title="__st('cart.title')" :mobile-title="__st('souqify_pages.cart.my_cart')" nav="cart" page="cart">
    <div class="sq-wrap pb-40 pt-4 lg:pb-16 lg:pt-6" data-cart-page data-fragment-url="{{ route('storefront.cart.fragment') }}">
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-5 hidden lg:block">
            <ol class="sq-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('cart.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('souqify_pages.cart.my_cart') }}</span></li>
            </ol>
        </nav>
        <h1 class="mb-4 text-xl font-medium text-sq-ink lg:sr-only">{{ __st('souqify_pages.cart.my_cart') }}</h1>

        <div class="sq-cart-grid grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px] xl:grid-cols-[minmax(0,1fr)_503px]">
            <div class="min-w-0" data-cart-region aria-live="polite" aria-busy="false">
                <x-sf::souqify.inner.cart.body :cart="$cart" />
            </div>

            <aside class="sq-cart-aside hidden lg:sticky lg:top-28 lg:block" aria-label="{{ __st('cart.summary') }}" data-cart-summary-region>
                <x-sf::souqify.inner.cart.summary :cart="$cart" />
            </aside>
        </div>
    </div>

    @if ($band !== [])
        {{-- "Add some items to “Get Free Shipping”" band (1:75039) --}}
        <section class="sq-band mb-12" aria-labelledby="sq-band-title">
            <div class="sq-wrap grid gap-6 py-10 lg:py-14">
                <h2 id="sq-band-title" class="text-2xl font-medium text-white lg:text-[40px]" data-reveal="fade-up">{!! __st('souqify_pages.cart.band_title', ['highlight' => '<span class="text-sq-band-ink">'.e(__st('souqify_pages.cart.band_highlight')).'</span>']) !!}</h2>
                <div class="swiper w-full" data-rail='{"spaceBetween":12,"breakpoints":{"1024":{"spaceBetween":24}}}' data-reveal="fade-up">
                    <ul class="swiper-wrapper" role="list">
                        @foreach ($band as $card)
                            <li class="swiper-slide !w-[172px] lg:!w-[332px]"><x-sf::product-card :card="$card" /></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endif

    @if ($empty && $cards !== [])
        <section class="sq-wrap grid gap-4 pb-16" aria-label="{{ __st('souqify_pages.cart.you_may_like') }}">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-4" data-reveal-stagger>
                @foreach (array_slice($cards, 0, 4) as $card)
                    <div data-reveal="fade-up"><x-sf::product-card :card="$card" /></div>
                @endforeach
            </div>
        </section>
    @elseif ($rail !== [])
        <section class="sq-wrap grid gap-6 pb-16" aria-labelledby="sq-cart-picks-title">
            <div class="flex items-center justify-between gap-3" data-reveal="fade-up">
                <h2 id="sq-cart-picks-title" class="text-2xl font-medium text-sq-ink lg:text-[32px]">{{ __st('souqify_pages.product.recommended') }}</h2>
                <a href="{{ route('storefront.best-sellers') }}" class="inline-flex items-center gap-1 text-sm text-sq-ink hover:underline">{{ __st('souqify_pages.product.view_all') }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" /></a>
            </div>
            <div class="swiper -mx-4 px-4 lg:mx-0 lg:px-0" data-rail='{"spaceBetween":12,"breakpoints":{"1024":{"spaceBetween":24}}}' data-reveal="fade-up">
                <ul class="swiper-wrapper" role="list">
                    @foreach ($rail as $card)
                        <li class="swiper-slide !w-[172px] lg:!w-[300px]"><x-sf::product-card :card="$card" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-sf::layout>
