{{--
    Souqify checkout (Figma 1:75299 / m 1:76569; canonical Storefront additions 98:101297 +
    states 98:101571 / m 98:101659 + m states 98:101756 in Souqify mode; address modal
    97:102061, select address m 1:80503). Same inputs and hooks as the Ecommet checkout
    (CheckoutPage::data, commerce/checkout.js): the shared canonical address step (radio cards,
    add / select address modals, billing, pay button) with the payment section in its slot,
    inside the white "Shipping information" card; right column = the Souqify "Order Summary"
    card (items, totals, trust cards, payment badges).
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
    <div class="sq-wrap pb-16 pt-4 lg:pt-6" data-checkout-page>
        <script type="application/json" data-checkout-page-config>@json($config)</script>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-4 hidden lg:block">
            <ol class="sq-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.cart') }}">{{ __st('cart.title') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('checkout.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="sr-only">{{ __st('checkout.title') }}</h1>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_420px] xl:grid-cols-[minmax(0,740px)_524px] lg:justify-between lg:gap-10">
            <form class="sq-card min-w-0 p-4 lg:p-6" data-checkout-form novalidate data-reveal="fade-up">
                <x-sf::checkout.address-step :addresses="$addresses" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls"
                    :shipping="$shipping" :gateway-available="$gateways !== []" :pay-label="__st('checkout.pay_total', ['total' => $payTotal])">
                    <x-slot:payment>
                        <div class="lg:hidden" data-checkout-summary-region>
                            <x-sf::souqify.inner.checkout.summary :cart="$cart" />
                        </div>
                        <x-sf::checkout.payment :gateways="$gateways" />
                        @if ($addresses === [])
                            <p class="text-center text-sm font-medium text-red-600" data-no-address-note>{{ __st('checkout.no_address') }}</p>
                        @endif
                    </x-slot:payment>
                </x-sf::checkout.address-step>
            </form>

            <aside class="hidden lg:sticky lg:top-28 lg:block" aria-label="{{ __st('checkout.summary') }}" data-reveal="fade-up">
                <div data-checkout-summary-region>
                    <x-sf::souqify.inner.checkout.summary :cart="$cart" />
                </div>
            </aside>
        </div>

        <div id="checkout-overlay" class="fixed inset-0 z-[80] grid cursor-wait place-items-center bg-white/70 backdrop-blur-[2px]" data-checkout-overlay hidden>
            <div class="grid justify-items-center gap-3 text-sm font-medium text-sf-ink" role="status">
                <span class="size-10 animate-spin rounded-full border-[3px] border-sq-primary border-e-transparent motion-reduce:animate-none" aria-hidden="true"></span>
                {{ __st('checkout.processing') }}
            </div>
        </div>
    </div>
</x-sf::layout>
