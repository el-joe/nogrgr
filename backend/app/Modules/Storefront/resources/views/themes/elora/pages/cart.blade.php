{{--
    Elora cart (Figma 1:17541 / m 1:22029; empty m 1:23315). Inputs as the Ecommet cart:
    $cart (Store API cart payload), $rails (similar / viewed / recommended cards), $seo. The body and
    the summary are swapped in by commerce/cart-page.js after each Store API mutation.
--}}
<x-sf::layout :seo="$seo" :title="__st('cart.title')" :mobile-title="__st('elora_pages.cart.my_cart')" nav="cart" page="cart">
    <div class="elo-cart-wrap" data-cart-page data-fragment-url="{{ route('storefront.cart.fragment') }}">
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="elo-cart-crumbs max-lg:hidden">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('cart.home') }}</a><x-ui::icon name="elo-arrow-right" :size="16" /></li>
                <li><span aria-current="page">{{ __st('cart.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="sr-only">{{ __st('elora_pages.cart.my_cart') }}</h1>

        <div class="elo-cart-grid">
            <div class="elo-cart-region" data-cart-region aria-live="polite" aria-busy="false">
                <x-sf::elora.inner.cart.body :cart="$cart" />
            </div>

            <aside class="elo-cart-aside" aria-label="{{ __st('cart.summary') }}">
                <div class="max-md:hidden" data-cart-summary-region>
                    <x-sf::elora.inner.cart.summary :cart="$cart" />
                </div>
                @php($badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges(wallets: true))
                @if ($badges)
                    <section class="elo-cart-pay max-md:hidden" aria-labelledby="elo-cart-pay">
                        <h2 id="elo-cart-pay">1. {{ __st('elora_pages.cart.payment_methods') }}</h2>
                        <ul role="list">
                            @foreach ($badges as $badge)
                                <li title="{{ __st('shell.payments.'.$badge) }}">
                                    @if (in_array($badge, ['visa', 'mastercard', 'applepay', 'fawry'], true))
                                        <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/payments/'.$badge.'.png') }}" alt="{{ __st('shell.payments.'.$badge) }}" width="56" height="37" loading="lazy" decoding="async">
                                    @else
                                        <span>{{ __st('shell.payments.'.$badge) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>

    {{-- Rails (re-rendered by `storefront.cart.fragment` when the products in the cart change). --}}
    <div data-cart-rails data-rails-key="{{ collect($cart['lines'])->pluck('store_product_id')->unique()->sort()->implode(',') }}">
        <x-sf::elora.inner.cart.rails :rails="$rails" />
    </div>
</x-sf::layout>
