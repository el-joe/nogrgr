{{--
    Account › Addresses (spec §1–§2, Figma Storefront additions §6). Inputs: $addresses
    (CustomerAddressResource arrays, default first), $countries (store countries),
    $phoneCountry (ISO2). The JSON island #address-book carries the same data and the
    endpoint URLs (with __ID__ / __COUNTRY__ templates) for the address-book script.
--}}
@php
    $urls = [
        'list' => route('storefront.account.addresses.list'),
        'store' => route('storefront.account.addresses.store'),
        'update' => route('storefront.account.addresses.update', ['address' => '__ID__']),
        'destroy' => route('storefront.account.addresses.destroy', ['address' => '__ID__']),
        'default' => route('storefront.account.addresses.default', ['address' => '__ID__']),
        'cities' => route('storefront.geo.cities', ['country' => '__COUNTRY__']),
        'reverse' => route('storefront.geo.reverse'),
        'ip' => route('storefront.geo.ip'),
    ];
    $addressBook = ['addresses' => $addresses, 'countries' => $countries, 'phoneCountry' => $phoneCountry, 'urls' => $urls];
    $template = ['id' => '__ID__', 'label' => '', 'full_name' => '', 'phone' => '', 'one_line' => '', 'is_default' => false];
@endphp
<x-sf::account.layout :title="__('identity::addresses.title')" active="addresses" page="addresses">
    <x-slot:actions>
        <x-sf::form.button icon="plus" size="sm" class="hidden lg:inline-flex" data-address-add>{{ __st('account.add_address') }}</x-sf::form.button>
    </x-slot:actions>

    <script type="application/json" id="address-book">@json($addressBook)</script>

    <section class="grid gap-4" aria-label="{{ __('identity::addresses.list_title') }}" data-address-book data-mode="manage" data-modal="sf-address-modal">
        <div class="grid gap-4" data-address-list>
            @foreach ($addresses as $address)
                <x-sf::address.card :address="$address" mode="manage" />
            @endforeach
        </div>
        <div class="sf-card grid justify-items-center gap-3 p-8 text-center" data-address-empty @if (count($addresses) > 0) hidden @endif>
            <x-ui::icon name="map" :size="28" class="text-sf-muted" />
            <p class="text-sm text-sf-muted">{{ __('identity::addresses.empty') }}</p>
        </div>
        <button type="button" class="sf-add-dashed" data-address-add><x-ui::icon name="plus" :size="16" />{{ __st('account.add_address') }}</button>
        <template data-address-template><x-sf::address.card :address="$template" mode="manage" /></template>
    </section>

    <x-sf::address.modal :countries="$countries" :phone-country="$phoneCountry" :urls="$urls" />
</x-sf::account.layout>
