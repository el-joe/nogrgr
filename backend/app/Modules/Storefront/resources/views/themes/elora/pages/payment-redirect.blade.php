{{--
    Elora family copy (same markup / hooks, Elora tokens).
    Redirecting to the payment provider (SF-ECO-023b, Storefront additions 114:108887 /
    m 114:109089). Inputs: $order, $numbers, $total (money), $gateway (redirect gateway).
    commerce/payment.js starts the payment (POST /store-api/v1/payments/{order}/start) and
    follows the provider URL; the link is the manual fallback.
--}}
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@extends('storefront::themes.elora.pages.errors.layout', ['title' => __st('checkout.redirect.title')])

@section('content')
    <div class="contents" data-payment-redirect data-start="{{ route('store-api.v1.payments.start', ['order' => $order->uuid]) }}" data-gateway="{{ $gateway['code'] }}"
        data-currency="{{ app(\App\Modules\Storefront\Services\StorefrontContext::class)->currencyCode() }}"
        data-failed="{{ route('storefront.checkout.payment.return', ['gateway' => $gateway['code'], 'order' => $order->uuid, 'status' => 'failed']) }}">
        <span class="size-12 animate-spin rounded-full border-[3px] border-elo-primary border-e-transparent motion-reduce:animate-none" aria-hidden="true" data-redirect-spinner></span>
        <h1 class="mt-2 text-2xl font-semibold text-sf-ink lg:text-[28px]" role="status">{{ __st('checkout.redirect.title') }}</h1>
        <p class="text-sm text-sf-muted">{{ __st('checkout.redirect.keep_open') }}</p>
        <div class="grid w-full max-w-[400px] gap-2 rounded-xl border border-sf-border p-4 text-start">
            <p class="flex items-center justify-between gap-3"><span class="text-sm text-sf-muted">{{ __st('checkout.order_number', ['number' => $numbers]) }}</span><span class="text-lg font-semibold text-sf-ink">{{ StorefrontCart::price($total) }}</span></p>
            <p class="flex items-center gap-2 text-xs text-sf-muted"><x-ui::icon name="shield" :size="16" class="text-elo-green" />{{ __st('checkout.redirect.secure', ['gateway' => $gateway['name']]) }}</p>
        </div>
        <a href="{{ route('storefront.checkout.pay', ['order' => $order->uuid]) }}" class="mt-2 text-sm font-medium text-elo-primary hover:underline" data-redirect-continue>{{ __st('checkout.redirect.continue') }}</a>
        <a href="{{ route('storefront.cart') }}" class="text-xs text-sf-muted hover:text-sf-ink hover:underline">{{ __st('checkout.redirect.cancel') }}</a>
    </div>
@endsection
