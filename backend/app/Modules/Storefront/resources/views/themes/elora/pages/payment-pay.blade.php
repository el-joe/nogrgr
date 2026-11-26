{{--
    Elora family copy (same markup / hooks, Elora tokens).
    Pay an already placed order (SF-ECO-023b retry / "Complete payment" of the order page):
    order number + total, the payment section of the old project (spec 08) and the pay
    button. Inputs: $order, $numbers, $total, $gateways, $gateway (preselected inline one).
    Inline gateways pay here (Stripe card, decision #78 b), redirect gateways go to the redirect page.
--}}
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $config = [
        'start' => route('store-api.v1.payments.start', ['order' => $order->uuid]),
        'pay' => route('storefront.checkout.pay', ['order' => '__ORDER__']),
        'return' => url('/checkout/payment/__GATEWAY__/__ORDER__'),
        'order' => $order->uuid,
        'currency' => app(\App\Modules\Storefront\Services\StorefrontContext::class)->currencyCode(),
        'messages' => [
            'processing' => __st('checkout.processing'),
            'select_method' => __st('checkout.payment.select'),
            'card_error' => __st('checkout.card.error'),
            'gateway_blocked_title' => __st('checkout.toasts.gateway_blocked_title'),
            'gateway_blocked' => __st('checkout.toasts.gateway_blocked'),
            'failed_title' => __st('checkout.toasts.failed_title'),
            'failed' => __st('checkout.toasts.failed'),
        ],
    ];
@endphp
<x-sf::layout :title="__st('checkout.pay_title')" :mobile-title="__st('checkout.pay_title')" :back="route('storefront.cart')" page="payment">
    <div class="elo-wrap grid justify-items-center pb-16 pt-6 lg:pt-10" data-payment-page>
        <script type="application/json" data-checkout-page-config>@json($config)</script>
        <form class="grid w-full max-w-[560px] gap-6" data-pay-form novalidate>
            <h1 class="text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('checkout.pay_title') }}</h1>
            <div class="flex items-center justify-between gap-3 rounded-sf-card border border-sf-border p-4">
                <span class="text-sm text-sf-muted">{{ __st('checkout.order_number', ['number' => $numbers]) }}</span>
                <span class="text-lg font-semibold text-sf-ink">{{ StorefrontCart::price($total) }}</span>
            </div>
            <x-sf::checkout.payment :gateways="$gateways" :charge="$total" />
            <button type="submit" class="sf-btn sf-btn-cta sf-btn-lg w-full" data-pay-button @disabled($gateways === [])>{{ __st('checkout.pay_total', ['total' => StorefrontCart::price($total)]) }}</button>
            @if ($gateway)
                <input type="hidden" value="{{ $gateway['code'] }}" data-preselect-gateway>
            @endif
        </form>
        <div id="checkout-overlay" class="fixed inset-0 z-[80] grid cursor-wait place-items-center bg-white/70 backdrop-blur-[2px]" data-checkout-overlay hidden>
            <div class="grid justify-items-center gap-3 text-sm font-medium text-sf-ink" role="status">
                <span class="size-10 animate-spin rounded-full border-[3px] border-elo-primary border-e-transparent motion-reduce:animate-none" aria-hidden="true"></span>
                {{ __st('checkout.processing') }}
            </div>
        </div>
    </div>
</x-sf::layout>
