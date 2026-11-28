{{--
    Checkout address step (spec §2, Figma Storefront additions §3) — mounted by the
    Phase 5 checkout. Radio cards (default pill, edit), dashed "Add new address", empty
    state, shipping info box, "Billing address is same as shipping" + second list, and
    the pay button with its disabled reason. Payment methods stay a slot (ported 1:1 from
    the old project later, 08-checkout-payment-spec.md).

    Props:
      addresses     CustomerAddressResource arrays            countries / phoneCountry  for the modal
      urls          store, update (__ID__), cities (__COUNTRY__), reverse, ip
      shipping      ['state' => ok|no_country|unavailable|not_sold, 'label' => 'Standard … $6.00', 'products' => [...]]
      gatewayAvailable  bool        payLabel  "Pay $139.20"
    Hidden inputs: shipping_address_id, billing_address_id, billing_same_as_shipping.
    Events (bubbling, on the root): checkout:address-change {shipping, billing, billingSame};
    listen: checkout:shipping {state, label, products} to update the box and pay button.
--}}
@props([
    'addresses' => [],
    'countries' => [],
    'phoneCountry' => null,
    'urls' => [],
    'shipping' => ['state' => 'ok', 'label' => null, 'products' => []],
    'gatewayAvailable' => true,
    'payLabel' => null,
    'id' => 'sf-checkout-address',
])
@php
    $default = collect($addresses)->firstWhere('is_default', true) ?? ($addresses[0] ?? null);
    $selected = $default['id'] ?? null;
    // Same order as the script: no address → shipping problem → no gateway (no flash before JS runs).
    $payBlocked = $selected === null || ($shipping['state'] ?? 'ok') !== 'ok' || ! $gatewayAvailable;
    $template = ['id' => '__ID__', 'label' => '', 'full_name' => '', 'phone' => '', 'one_line' => '', 'is_default' => false];
    $config = [
        'addresses' => $addresses,
        'countries' => $countries,
        'phoneCountry' => $phoneCountry,
        'urls' => $urls,
        'shipping' => $shipping,
        'gatewayAvailable' => (bool) $gatewayAvailable,
        'messages' => [
            'no_country' => __st('checkout.shipping.no_country'),
            'unavailable' => __st('checkout.shipping.unavailable'),
            'not_sold' => __st('checkout.shipping.not_sold', ['products' => '__PRODUCTS__']),
            'no_band' => __st('checkout.shipping.no_band'),
            'reason_address' => __st('checkout.reasons.no_address'),
            'reason_shipping' => __st('checkout.reasons.shipping'),
            'reason_gateway' => __st('checkout.reasons.no_gateway'),
        ],
    ];
@endphp
<div id="{{ $id }}" class="grid gap-5" data-checkout-address data-address-book data-mode="select" data-modal="{{ $id }}-modal" {{ $attributes }}>
    <script type="application/json" data-checkout-config>@json($config)</script>
    <input type="hidden" name="shipping_address_id" value="{{ $selected }}" data-shipping-id>
    <input type="hidden" name="billing_address_id" value="{{ $selected }}" data-billing-id>
    <input type="hidden" name="billing_same_as_shipping" value="1" data-billing-same-value>

    {{-- Shipping address --}}
    <section class="grid gap-3" aria-labelledby="{{ $id }}-shipping-title" data-address-group="shipping">
        <h2 id="{{ $id }}-shipping-title" class="text-lg font-semibold text-sf-ink">{{ __st('checkout.shipping_address') }}</h2>
        <div class="grid gap-3" data-address-list role="radiogroup" aria-labelledby="{{ $id }}-shipping-title">
            @foreach ($addresses as $address)
                <x-sf::address.card :address="$address" mode="select" group="{{ $id }}-shipping" :selected="$address['id'] === $selected" />
            @endforeach
        </div>
        <div class="sf-card grid justify-items-center gap-3 p-8 text-center" data-address-empty @if (count($addresses) > 0) hidden @endif>
            <x-ui::icon name="map" :size="28" class="text-sf-muted" />
            <p class="text-sm text-sf-muted">{{ __('identity::addresses.empty') }}</p>
            <button type="button" class="sf-add-dashed px-6" data-address-add data-address-purpose="shipping"><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
        </div>
        <button type="button" class="sf-add-dashed" data-address-add data-address-purpose="shipping" @if (count($addresses) === 0) hidden @endif data-add-when-filled><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
        <template data-address-template><x-sf::address.card :address="$template" mode="select" group="{{ $id }}-shipping" /></template>
    </section>

    {{-- Shipping info box --}}
    <div data-shipping-info aria-live="polite">
        @isset($shippingInfo)
            {{ $shippingInfo }}
        @else
            <div class="sf-alert sf-alert-success w-fit" data-shipping-ok @if (($shipping['state'] ?? 'ok') !== 'ok' || empty($shipping['label'])) hidden @endif>
                <x-ui::icon name="truck" :size="18" class="mt-px shrink-0" /><span data-shipping-label>{{ $shipping['label'] ?? '' }}</span>
            </div>
            <x-sf::alert tone="danger" data-shipping-error :hidden="($shipping['state'] ?? 'ok') === 'ok'"><span data-shipping-error-text></span></x-sf::alert>
        @endisset
    </div>

    {{-- Billing --}}
    <label class="inline-flex items-center gap-2 text-sm text-sf-ink"><input type="checkbox" class="sf-check" checked data-billing-same>{{ __('identity::addresses.billing_same') }}</label>
    <section class="grid gap-3" aria-labelledby="{{ $id }}-billing-title" data-address-group="billing" hidden>
        <h2 id="{{ $id }}-billing-title" class="text-lg font-semibold text-sf-ink">{{ __st('checkout.billing_address') }}</h2>
        <div class="grid gap-3" data-address-list role="radiogroup" aria-labelledby="{{ $id }}-billing-title">
            @foreach ($addresses as $address)
                <x-sf::address.card :address="$address" mode="select" group="{{ $id }}-billing" :selected="$address['id'] === $selected" />
            @endforeach
        </div>
        <button type="button" class="sf-add-dashed" data-address-add data-address-purpose="billing"><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
        <template data-address-template><x-sf::address.card :address="$template" mode="select" group="{{ $id }}-billing" /></template>
    </section>

    {{-- Payment methods: provided by the checkout (old-project markup, no redesign) --}}
    {{ $payment ?? '' }}

    <div class="grid gap-2">
        <button type="submit" class="sf-btn sf-btn-cta sf-btn-lg w-full" data-pay-button aria-describedby="{{ $id }}-pay-reason" @disabled($payBlocked)>{{ $payLabel ?? __st('checkout.pay') }}</button>
        <p id="{{ $id }}-pay-reason" class="flex items-center gap-1.5 text-xs text-sf-danger" data-pay-reason hidden><x-ui::icon name="info" :size="14" /><span></span></p>
    </div>

    {{-- A <template>: the address <form> may not nest inside the checkout <form>; the script mounts it on <body>. --}}
    <template data-address-modal-template><x-sf::address.modal :id="$id.'-modal'" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls" /></template>
</div>
