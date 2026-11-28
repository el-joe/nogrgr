{{--
    Elora checkout shell (Figma 1:18245 / m 1:22186): the address step, billing and the payment
    slot on the left, the summary / pay button / trust cards in the `aside` slot on the right
    (one grid; on phones both columns flatten and the blocks are ordered with CSS). Same hooks,
    config and events as x-sf::checkout.address-step (core/account/checkout.js + address-book.js):
    radio cards, add / edit modal, shipping info box, billing-same, pay button + reason.
    "Change Address" expands the saved addresses; the selected one shows as the Figma box.
--}}
@props([
    'addresses' => [],
    'countries' => [],
    'phoneCountry' => null,
    'urls' => [],
    'shipping' => ['state' => 'ok', 'label' => null, 'products' => []],
    'gatewayAvailable' => true,
    'id' => 'sf-checkout-address',
])
@php
    $default = collect($addresses)->firstWhere('is_default', true) ?? ($addresses[0] ?? null);
    $selected = $default['id'] ?? null;
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
<div id="{{ $id }}" class="elc" data-checkout-address data-address-book data-mode="select" data-modal="{{ $id }}-modal" data-elc-step {{ $attributes }}>
    <script type="application/json" data-checkout-config>@json($config)</script>
    <input type="hidden" name="shipping_address_id" value="{{ $selected }}" data-shipping-id>
    <input type="hidden" name="billing_address_id" value="{{ $selected }}" data-billing-id>
    <input type="hidden" name="billing_same_as_shipping" value="1" data-billing-same-value>

    <div class="elc-main">
        <section class="elc-ship" aria-labelledby="{{ $id }}-shipping-title" data-address-group="shipping" data-elc-addresses data-collapsed>
            <div class="elc-head">
                <h2 id="{{ $id }}-shipping-title">{{ __st('elora_pages.checkout.shipping_info') }}</h2>
                <button type="button" class="elc-change" data-elc-change aria-expanded="false" @if (count($addresses) === 0) hidden @endif>
                    <span>{{ __st('elora_pages.checkout.change_address') }}</span><x-ui::icon name="elo-chevron-right" :size="24" />
                </button>
            </div>
            <div class="elc-list" data-address-list role="radiogroup" aria-labelledby="{{ $id }}-shipping-title">
                @foreach ($addresses as $address)
                    <x-sf::address.card :address="$address" mode="select" edit-icon="elo-edit" :edit-size="24" group="{{ $id }}-shipping" :selected="$address['id'] === $selected" />
                @endforeach
            </div>
            <div class="sf-card grid justify-items-center gap-3 p-8 text-center" data-address-empty @if (count($addresses) > 0) hidden @endif>
                <x-ui::icon name="map" :size="28" class="text-sf-muted" />
                <p class="text-sm text-sf-muted">{{ __('identity::addresses.empty') }}</p>
                <button type="button" class="sf-add-dashed px-6" data-address-add data-address-purpose="shipping"><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
            </div>
            <button type="button" class="sf-add-dashed elc-add" data-address-add data-address-purpose="shipping" @if (count($addresses) === 0) hidden @endif data-add-when-filled><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
            <template data-address-template><x-sf::address.card :address="$template" mode="select" edit-icon="elo-edit" :edit-size="24" group="{{ $id }}-shipping" /></template>
        </section>

        <div class="elc-ship-info" data-shipping-info aria-live="polite">
            <div class="sf-alert sf-alert-success w-fit" data-shipping-ok @if (($shipping['state'] ?? 'ok') !== 'ok' || empty($shipping['label'])) hidden @endif>
                <x-ui::icon name="truck" :size="18" class="mt-px shrink-0" /><span data-shipping-label>{{ $shipping['label'] ?? '' }}</span>
            </div>
            <x-sf::alert tone="danger" data-shipping-error :hidden="($shipping['state'] ?? 'ok') === 'ok'"><span data-shipping-error-text></span></x-sf::alert>
        </div>

        {{ $payment ?? '' }}

        <div class="elc-billing">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="sf-check" checked data-billing-same>{{ __('identity::addresses.billing_same') }}</label>
            <section class="grid gap-3" aria-labelledby="{{ $id }}-billing-title" data-address-group="billing" hidden>
                <h2 id="{{ $id }}-billing-title" class="text-lg font-medium text-elo-ink">{{ __st('checkout.billing_address') }}</h2>
                <div class="grid gap-3" data-address-list role="radiogroup" aria-labelledby="{{ $id }}-billing-title">
                    @foreach ($addresses as $address)
                        <x-sf::address.card :address="$address" mode="select" edit-icon="elo-edit" :edit-size="24" group="{{ $id }}-billing" :selected="$address['id'] === $selected" />
                    @endforeach
                </div>
                <button type="button" class="sf-add-dashed" data-address-add data-address-purpose="billing"><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
                <template data-address-template><x-sf::address.card :address="$template" mode="select" edit-icon="elo-edit" :edit-size="24" group="{{ $id }}-billing" /></template>
            </section>
        </div>
    </div>

    <div class="elc-side">{{ $aside ?? '' }}</div>

    {{-- A <template>: the address <form> may not nest inside the checkout <form>; the script mounts it on <body>. --}}
    <template data-address-modal-template><x-sf::address.modal :id="$id.'-modal'" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls" /></template>
</div>
