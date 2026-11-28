{{--
    Account › Addresses (spec §1–§2, Figma Storefront additions §6). Inputs: $addresses
    (CustomerAddressResource arrays, default first), $countries (store countries),
    $phoneCountry (ISO2).
--}}
<x-sf::account.layout :title="__('identity::addresses.title')" active="addresses" page="addresses">
    <x-slot:actions>
        <x-sf::form.button icon="plus" size="sm" class="hidden lg:inline-flex" data-address-add>{{ __st('account.add_address') }}</x-sf::form.button>
    </x-slot:actions>

    <x-sf::account.address-book :addresses="$addresses" :countries="$countries" :phone-country="$phoneCountry" />
</x-sf::account.layout>
