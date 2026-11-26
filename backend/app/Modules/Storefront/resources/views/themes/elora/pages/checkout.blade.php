{{--
    Elora checkout (Figma 1:18245 / m 1:22186; canonical Storefront additions 98:100761 +
    states 98:101058 / m 98:101146 in Elora mode). Same inputs and hooks as the Ecommet
    checkout (CheckoutPage::data, commerce/checkout.js): the shared canonical address step
    (radio cards, add / select address modals 97:101294, billing, pay button) with the
    payment section in its slot; right column = the Elora summary + trust cards.
--}}
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $urls = [
        'list' => route('storefront.account.addresses.list'),
        'store' => route('storefront.account.addresses.store'),
        'update' => route('storefront.account.addresses.update', ['address' => '__ID__']),
        'cities' => route('storefront.geo.cities', ['country' => '__COUNTRY__']),
        'reverse' => route('storefront.geo.reverse'),
        'ip' => route('storefront.geo.ip'),
    ];
    $payTotal = StorefrontCart::price($cart['totals']['grand_total']);
    $config = [
        'fragment' => route('storefront.checkout.fragment'),
        'place' => route('store-api.v1.checkout.place'),
        'start' => route('store-api.v1.payments.start', ['order' => '__ORDER__']),
        'pay' => route('storefront.checkout.pay', ['order' => '__ORDER__']),
        'return' => url('/checkout/payment/__GATEWAY__/__ORDER__'),
        'coupon' => route('store-api.v1.cart.coupon.apply'),
        'cart' => route('storefront.cart'),
        'currency' => app(\App\Modules\Storefront\Services\StorefrontContext::class)->currencyCode(),
        'block' => $block,
        'messages' => [
            'pay' => __st('checkout.pay_total', ['total' => '__TOTAL__']),
            'processing' => __st('checkout.processing'),
            'select_method' => __st('checkout.payment.select'),
            'card_error' => __st('checkout.card.error'),
            'unavailable_items' => __st('checkout.reasons.unavailable_items'),
            'coupon_invalid' => __st('checkout.reasons.coupon_invalid'),
            'empty_cart' => __st('checkout.reasons.empty_cart'),
            'gateway_blocked_title' => __st('checkout.toasts.gateway_blocked_title'),
            'gateway_blocked' => __st('checkout.toasts.gateway_blocked'),
            'failed_title' => __st('checkout.toasts.failed_title'),
            'failed' => __st('checkout.toasts.failed'),
        ],
    ];
@endphp
<x-sf::layout :seo="$seo" :title="__st('checkout.title')" :mobile-title="__st('checkout.title')" :back="route('storefront.cart')" page="checkout">
    <div class="elo-narrow pb-16 pt-4 lg:pt-6" data-checkout-page>
        <script type="application/json" data-checkout-page-config>@json($config)</script>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-4 hidden lg:block">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.cart') }}">{{ __st('cart.title') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('checkout.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="mb-6 hidden text-[26px] font-medium text-elo-ink lg:block">{{ __st('checkout.title') }}</h1>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px] xl:grid-cols-[minmax(0,740px)_420px] lg:justify-between lg:gap-10">
            <form class="min-w-0" data-checkout-form novalidate>
                <x-sf::checkout.address-step :addresses="$addresses" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls"
                    :shipping="$shipping" :gateway-available="$gateways !== []" :pay-label="__st('checkout.pay_total', ['total' => $payTotal])">
                    <x-slot:payment>
                        <div class="lg:hidden" data-checkout-summary-region>
                            <x-sf::elora.inner.checkout.summary :cart="$cart" />
                        </div>
                        <x-sf::checkout.payment :gateways="$gateways" :charge="$cart['totals']['grand_total']" />
                        @if ($addresses === [])
                            <p class="text-center text-sm font-medium text-red-600" data-no-address-note>{{ __st('checkout.no_address') }}</p>
                        @endif
                    </x-slot:payment>
                </x-sf::checkout.address-step>
            </form>

            <aside class="hidden gap-5 lg:sticky lg:top-28 lg:grid" aria-label="{{ __st('checkout.summary') }}">
                <div data-checkout-summary-region>
                    <x-sf::elora.inner.checkout.summary :cart="$cart" />
                </div>
                <x-sf::elora.inner.product-trust />
            </aside>
        </div>

        <div id="checkout-overlay" class="fixed inset-0 z-[80] grid cursor-wait place-items-center bg-white/70 backdrop-blur-[2px]" data-checkout-overlay hidden>
            <div class="grid justify-items-center gap-3 text-sm font-medium text-sf-ink" role="status">
                <span class="size-10 animate-spin rounded-full border-[3px] border-elo-primary border-e-transparent motion-reduce:animate-none" aria-hidden="true"></span>
                {{ __st('checkout.processing') }}
            </div>
        </div>
    </div>
    @include('storefront::themes.elora.partials.privacy-popup')
</x-sf::layout>
