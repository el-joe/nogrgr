{{--
    Elora cart (Figma 1:17541 / m 1:22029; empty m 1:23315). Inputs as the Ecommet cart:
    $cart (Store API cart payload), $picks (best sellers), $picksMoreUrl, $seo. The body and
    the summary are swapped in by commerce/cart-page.js after each Store API mutation.
--}}
<x-sf::layout :seo="$seo" :title="__st('cart.title')" :mobile-title="__st('elora_pages.cart.my_cart')" nav="cart" page="cart">
    <div class="elo-narrow pb-40 pt-4 lg:pb-16 lg:pt-6" data-cart-page data-fragment-url="{{ route('storefront.cart.fragment') }}">
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-5 hidden lg:block">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('cart.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('cart.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="mb-4 text-xl font-medium text-elo-ink lg:sr-only">{{ __st('elora_pages.cart.my_cart') }}</h1>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-10 xl:grid-cols-[minmax(0,797px)_409px] xl:justify-between">
            <div class="min-w-0" data-cart-region aria-live="polite" aria-busy="false">
                <x-sf::elora.inner.cart.body :cart="$cart" />
            </div>

            <aside class="grid gap-4 lg:sticky lg:top-28" aria-label="{{ __st('cart.summary') }}">
                <div class="hidden lg:block" data-cart-summary-region>
                    <x-sf::elora.inner.cart.summary :cart="$cart" />
                </div>
                @php($badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges())
                @if ($badges)
                    <section class="elo-summary hidden gap-3 lg:grid" aria-labelledby="elo-cart-pay">
                        <h2 id="elo-cart-pay" class="text-base text-elo-ink">{{ __st('elora_pages.cart.payment_methods') }}</h2>
                        <ul class="flex flex-wrap gap-2" role="list">
                            @foreach ($badges as $badge)
                                <li class="elo-pay-chip h-8 border border-elo-line bg-white">{{ __st('shell.payments.'.$badge) }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>

        @unless ($picks->isEmpty())
            <section class="mt-12 grid gap-4" aria-labelledby="elo-cart-picks-title">
                <h2 id="elo-cart-picks-title" class="text-lg font-medium text-elo-ink lg:text-xl" data-reveal="fade-up">{{ __st('elora_pages.cart.recommended') }}</h2>
                <div id="elo-cart-picks" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4 xl:grid-cols-6" data-reveal-stagger>
                    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $picks->cards, 'variant' => 'grid'])
                </div>
                <x-sf::section.load-more :url="$picksMoreUrl" target="#elo-cart-picks" class="mt-4" />
            </section>
        @endunless
    </div>
</x-sf::layout>
