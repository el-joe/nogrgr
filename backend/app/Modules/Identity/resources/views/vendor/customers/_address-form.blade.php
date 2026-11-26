{{--
    Address create / edit (VEN-025c, Figma Vendor Panel 84:107236 "Add address"), loaded
    into #vendor-address-modal: country (the store's selling countries) → city (Select2,
    options of that country, data-city-select), street, label, default — plus the
    canonical address form's full name and phone (SaveCustomerAddressRequest).
--}}
<form
    id="vendor-address-form"
    method="POST"
    action="{{ $address->exists ? vendor_route('sales.customers.addresses.update', ['customer' => $customer->id, 'address' => $address->id]) : vendor_route('sales.customers.addresses.store', ['customer' => $customer->id]) }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('identity::customers.save') }}"
    data-address-form
>
    @csrf
    @if ($address->exists)
        @method('PUT')
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="country_id" id="vendor-address-country" :label="__('identity::addresses.fields.country_id')" :options="$countries" :selected="$countryId" :placeholder="__('identity::addresses.choose_country')" required data-country-select />
        <x-ui::select
            name="city"
            id="vendor-address-city"
            :label="__('identity::addresses.fields.city')"
            :options="$address->city ? [$address->city => $address->city] : []"
            :selected="$address->city"
            :placeholder="__('identity::customers.addresses.choose_city')"
            :helper="__('identity::customers.addresses.cities_help')"
            :ajax-url="$countryId ? vendor_route('sales.customers.cities', ['country_id' => $countryId]) : null"
            :data-cities-url="vendor_route('sales.customers.cities')"
            required
            data-city-select
        />
    </div>
    <x-ui::input name="address_line_1" :label="__('identity::customers.addresses.street')" :value="$address->address_line_1" required maxlength="300" :placeholder="__('identity::addresses.street_hint')" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="label" :label="__('identity::addresses.fields.label')" :options="$labels" :selected="$address->label" :placeholder="__('identity::customers.addresses.choose_label')" :search="false" allow-clear />
        <x-ui::input name="state" :label="__('identity::addresses.fields.state')" :value="$address->state" maxlength="100" />
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::input name="full_name" :label="__('identity::addresses.fields.full_name')" :value="$address->full_name" required maxlength="120" />
        <x-ui::phone name="phone" id="vendor-address-phone" :label="__('identity::addresses.fields.phone')" :value="$address->phone" :country="$phoneCountry" required />
    </div>
    <x-ui::checkbox name="is_default" :label="__('identity::addresses.fields.is_default')" :checked="(bool) $address->is_default" unchecked-value="0" />
</form>
