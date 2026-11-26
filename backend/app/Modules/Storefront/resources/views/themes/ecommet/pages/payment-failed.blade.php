{{--
    Payment was not completed (SF-ECO-023b, Storefront additions 114:108918 / m 114:109120):
    declined / failed return from the provider. Inputs: $order, $gateway (code).
    Try again → the pay step of the same order; Back to cart.
--}}
<x-sf::layout :title="__st('checkout.failed.title')" page="payment-failed">
    <div class="eco-container grid place-items-center pb-20 pt-16 lg:pt-24">
        <div class="grid w-full max-w-[420px] justify-items-center gap-4 text-center" data-reveal="fade-up">
            <span class="grid size-[72px] place-items-center rounded-full bg-sf-accent-subtle text-eco-red" aria-hidden="true"><x-ui::icon name="x-circle" :size="32" /></span>
            <h1 class="mt-2 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('checkout.failed.title') }}</h1>
            <p class="text-sm leading-6 text-sf-muted">{{ __st('checkout.failed.body') }}</p>
            <p class="flex w-full items-center gap-2 rounded-lg bg-sf-info-bg px-4 py-3 text-start text-sm text-sf-ink"><x-ui::icon name="info" :size="16" class="shrink-0 text-sf-info" />{{ __st('checkout.failed.saved', ['number' => $order->number, 'minutes' => config('commerce.reservation_minutes', 30)]) }}</p>
            <div class="mt-2 flex flex-wrap justify-center gap-3">
                <a href="{{ route('storefront.checkout.pay', ['order' => $order->uuid, 'gateway' => $gateway]) }}" class="sf-btn sf-btn-cta mo-press rounded-full px-6">{{ __st('checkout.failed.retry') }}</a>
                <a href="{{ route('storefront.cart') }}" class="sf-btn sf-btn-outline mo-press rounded-full px-6">{{ __st('checkout.failed.back_to_cart') }}</a>
            </div>
        </div>
    </div>
</x-sf::layout>
