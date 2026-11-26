{{--
    Cart (SF-ECO-022 / 022a; Figma 1:1348 · m 1:3210 · empty 1:1240 · m 1:3329).
    Inputs: $cart (Store API cart payload, StorefrontCart), $picks (ListingResult, "Explore
    {store}'s picks"), $picksMoreUrl, $seo. Body + summary are swapped in by
    resources/js/storefront/commerce/cart-page.js after each Store API mutation.
--}}
@php($store = \App\Modules\Storefront\Support\StorefrontTheme::storeName())
<x-sf::layout :seo="$seo" :title="__st('cart.title')" nav="cart" page="cart">
    <div class="eco-container pb-12 pt-4" data-cart-page data-fragment-url="{{ route('storefront.cart.fragment') }}">
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-6 hidden text-base text-eco-gray lg:block">
            <ol class="flex items-center gap-1">
                <li class="inline-flex items-center gap-1"><a href="{{ route('storefront.home') }}" class="hover:text-eco-black hover:underline">{{ __st('cart.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                <li><span class="text-eco-black" aria-current="page">{{ __st('cart.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="sr-only">{{ __st('cart.title') }}</h1>

        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,763px)_minmax(0,484px)] lg:justify-between lg:gap-10">
            <div class="grid min-w-0 gap-8">
                <div data-cart-region aria-live="polite" aria-busy="false">
                    <x-sf::cart.body :cart="$cart" />
                </div>

                @unless ($picks->isEmpty())
                    <section class="grid gap-4" aria-labelledby="sf-cart-picks-title">
                        <h2 id="sf-cart-picks-title" class="text-lg font-medium text-eco-black" data-reveal="fade-up">{{ __st('cart.picks', ['store' => $store]) }}</h2>
                        <div id="sf-cart-picks" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:gap-4" data-reveal-stagger>
                            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $picks->cards, 'variant' => 'grid'])
                        </div>
                        <x-sf::section.load-more :url="$picksMoreUrl" target="#sf-cart-picks" class="mt-4" />
                    </section>
                @endunless
            </div>

            <aside class="hidden bg-white px-8 py-8 shadow-[-4px_0_4px_rgba(195,195,195,0.25)] lg:block" aria-label="{{ __st('cart.summary') }}">
                <div data-cart-summary-region>
                    <x-sf::cart.summary :cart="$cart" />
                </div>
                <x-sf::cart.trust class="mt-6" />
            </aside>
        </div>
    </div>
</x-sf::layout>
