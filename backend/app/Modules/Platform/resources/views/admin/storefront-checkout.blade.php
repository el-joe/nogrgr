{{--
    Checkout address-step gallery (/admin/ui-kit/storefront-checkout?family=…&dir=…&empty=1
    &shipping=…&gateway=0): the real x-sf::checkout.address-step inside a checkout form,
    rendered with the chosen family's storefront bundle. Addresses live in the session
    (CheckoutDemoController); the payment block is a placeholder for the slot the Phase 5
    checkout fills with the old project's payment markup.
--}}
@php
    $t = fn (string $key, array $replace = []) => __('platform::checkout-demo.'.$key, $replace);
    $chip = fn (bool $active) => ['rounded-full border px-3 py-1', 'border-sf-cta bg-sf-cta text-sf-on-cta' => $active, 'border-sf-border text-sf-ink' => ! $active];
@endphp
<x-layouts.storefront :family="$family" :title="$t('title')">
    <main class="sf-page mx-auto grid max-w-7xl gap-8 px-4 pb-24 pt-6">
        <section class="sf-card grid gap-4 p-4 sm:p-6" aria-labelledby="demo-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 id="demo-title" class="text-2xl font-semibold text-sf-ink">{{ $t('title') }}</h1>
                <a href="{{ route('admin.ui-kit') }}" class="sf-link text-sm">{{ $t('back') }}</a>
            </div>
            <p class="max-w-3xl text-sm text-sf-muted">{{ $t('intro') }}</p>
            <div class="grid gap-3 text-sm">
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ $t('family') }}">
                    <span class="text-sf-muted">{{ $t('family') }}:</span>
                    @foreach ($families as $option)
                        <a href="{{ request()->fullUrlWithQuery(['family' => $option]) }}" @class([...$chip($option === $family), 'capitalize']) @if ($option === $family) aria-current="true" @endif>{{ $option }}</a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ $t('direction') }}">
                    <span class="text-sf-muted">{{ $t('direction') }}:</span>
                    @foreach (['ltr' => 'LTR · English', 'rtl' => 'RTL · العربية'] as $option => $label)
                        <a href="{{ request()->fullUrlWithQuery(['dir' => $option]) }}" @class($chip($option === $direction)) @if ($option === $direction) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ $t('shipping') }}">
                    <span class="text-sf-muted">{{ $t('shipping') }}:</span>
                    @foreach ($states as $option)
                        <a href="{{ request()->fullUrlWithQuery(['shipping' => $option]) }}" @class($chip($option === $shipping['state'])) @if ($option === $shipping['state']) aria-current="true" @endif>{{ $t('states.'.$option) }}</a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['empty' => $empty ? 0 : 1]) }}" @class($chip($empty)) @if ($empty) aria-current="true" @endif>{{ $t('empty') }}</a>
                    <a href="{{ request()->fullUrlWithQuery(['gateway' => $gatewayAvailable ? 0 : 1]) }}" @class($chip(! $gatewayAvailable)) @if (! $gatewayAvailable) aria-current="true" @endif>{{ $t('no_gateway') }}</a>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.ui-kit.checkout.pay') }}" class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]" data-ajax novalidate>
            @csrf
            <x-sf::checkout.address-step
                :addresses="$addresses"
                :countries="$countries"
                phone-country="SA"
                :urls="$urls"
                :shipping="$shipping"
                :gateway-available="$gatewayAvailable"
                :pay-label="$t('pay')"
            >
                <x-slot:payment>
                    <section class="grid gap-3" aria-labelledby="demo-payment">
                        <h2 id="demo-payment" class="text-lg font-semibold text-sf-ink">{{ $t('payment') }}</h2>
                        <div class="sf-card border-dashed p-5 text-sm text-sf-muted">{{ $t('payment_slot') }}</div>
                    </section>
                </x-slot:payment>
            </x-sf::checkout.address-step>

            <aside class="sf-card grid gap-3 p-5 lg:sticky lg:top-6" aria-labelledby="demo-summary">
                <h2 id="demo-summary" class="text-lg font-semibold text-sf-ink">{{ $t('summary') }}</h2>
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-sf-muted">{{ $t('subtotal') }}</dt><dd class="text-sf-ink">USD 133.20</dd></div>
                    <div class="flex justify-between"><dt class="text-sf-muted">{{ $t('shipping_fee') }}</dt><dd class="text-sf-ink">USD 6.00</dd></div>
                    <div class="flex justify-between border-t border-sf-border pt-2 font-semibold"><dt class="text-sf-ink">{{ $t('total') }}</dt><dd class="text-sf-ink">USD 139.20</dd></div>
                </dl>
            </aside>
        </form>
    </main>
    <x-ui::toasts />
</x-layouts.storefront>
