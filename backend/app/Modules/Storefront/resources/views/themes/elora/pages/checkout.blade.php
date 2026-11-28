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
    $payBlocked = ($addresses === []) || ($shipping['state'] ?? 'ok') !== 'ok' || $gateways === [];
    $storeName = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $termsUrl = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Terms->value]) : null;
    $privacyUrl = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Privacy->value]) : null;
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
            'store_order_limit' => __st('checkout.reasons.store_order_limit'),
            'empty_cart' => __st('checkout.reasons.empty_cart'),
            'gateway_blocked_title' => __st('checkout.toasts.gateway_blocked_title'),
            'gateway_blocked' => __st('checkout.toasts.gateway_blocked'),
            'failed_title' => __st('checkout.toasts.failed_title'),
            'failed' => __st('checkout.toasts.failed'),
        ],
    ];
@endphp
<x-sf::layout :seo="$seo" :title="__st('checkout.title')" :mobile-title="__st('elora_pages.cart.check_out')" :back="route('storefront.cart')" page="checkout">
    <div class="elo-narrow pb-16 pt-4 lg:pt-6" data-checkout-page>
        <script type="application/json" data-checkout-page-config>@json($config)</script>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-4 hidden lg:block">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.cart') }}">{{ __st('cart.title') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('checkout.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="sr-only">{{ __st('checkout.title') }}</h1>

        <form data-checkout-form novalidate>
            <x-sf::elora.inner.checkout.address-step :addresses="$addresses" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls"
                :shipping="$shipping" :gateway-available="$gateways !== []">
                <x-slot:payment>
                    <div class="elc-payment">
                        <x-sf::checkout.payment :gateways="$gateways" :charge="$cart['totals']['grand_total']" elora />
                        @if ($addresses === [])
                            <p class="text-center text-sm font-medium text-red-600" data-no-address-note>{{ __st('checkout.no_address') }}</p>
                        @endif
                    </div>
                </x-slot:payment>
                <x-slot:aside>
                    <div class="elc-summary-region" data-checkout-summary-region>
                        <x-sf::elora.inner.checkout.summary :cart="$cart" />
                    </div>
                    <div class="elc-pay">
                        <button type="submit" class="elc-pay-btn" data-pay-button aria-describedby="sf-checkout-address-pay-reason" @disabled($payBlocked)>{{ __st('checkout.pay_total', ['total' => $payTotal]) }}</button>
                        <p id="sf-checkout-address-pay-reason" class="elc-pay-reason" data-pay-reason hidden><x-ui::icon name="info" :size="14" /><span></span></p>
                    </div>
                    <p class="elc-powered">
                        <span>{{ __st('elora_pages.checkout.powered_by') }}</span> <strong>{{ $storeName }}</strong>
                        <span aria-hidden="true">|</span>
                        @if ($termsUrl)<a href="{{ $termsUrl }}">{{ __st('elora_pages.checkout.terms') }}</a>@endif
                        @if ($privacyUrl)<a href="{{ $privacyUrl }}">{{ __st('elora_pages.checkout.privacy') }}</a>@endif
                    </p>
                    <x-sf::elora.inner.product-trust class="elc-trust-grid" images />
                </x-slot:aside>
            </x-sf::elora.inner.checkout.address-step>
        </form>

        <div id="checkout-overlay" class="fixed inset-0 z-[80] grid cursor-wait place-items-center bg-white/70 backdrop-blur-[2px]" data-checkout-overlay hidden>
            <div class="grid justify-items-center gap-3 text-sm font-medium text-sf-ink" role="status">
                <span class="size-10 animate-spin rounded-full border-[3px] border-elo-primary border-e-transparent motion-reduce:animate-none" aria-hidden="true"></span>
                {{ __st('checkout.processing') }}
            </div>
        </div>
    </div>
    @include('storefront::themes.elora.partials.privacy-popup')
</x-sf::layout>
