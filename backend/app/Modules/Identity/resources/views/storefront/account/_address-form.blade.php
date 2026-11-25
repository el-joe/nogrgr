{{--
    Canonical address form include (kept for the backend contract): renders the shared
    x-sf::address.form. Inputs: $address (resource array or null), $countries,
    $phoneCountry, $formId, optional $urls.
--}}
<x-sf::address.form :id="$formId" :countries="$countries" :phone-country="$phoneCountry" :address="$address"
    :urls="$urls ?? [
        'store' => route('storefront.account.addresses.store'),
        'update' => route('storefront.account.addresses.update', ['address' => '__ID__']),
        'cities' => route('storefront.geo.cities', ['country' => '__COUNTRY__']),
        'reverse' => route('storefront.geo.reverse'),
        'ip' => route('storefront.geo.ip'),
    ]" />
