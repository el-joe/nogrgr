{{--
    Canonical address form (spec §1, Figma Storefront additions §1–2) shared by the
    account address book and the checkout address step. Field names and hooks follow
    the backend contract (SaveCustomerAddressRequest; data-address-form, data-address-map,
    data-phone-input / data-phone-country-field, data-country-select,
    data-city-suggestions-url). The address-book script switches action/method for edits.
--}}
@props(['id' => 'sf-address-form', 'countries' => [], 'phoneCountry' => null, 'address' => null, 'urls' => []])
@php
    $v = fn (string $field, mixed $default = null) => $address[$field] ?? $default;
@endphp
<form method="POST" action="{{ $urls['store'] ?? '#' }}" id="{{ $id }}" class="grid gap-4" novalidate
      data-address-form
      data-store-url="{{ $urls['store'] ?? '' }}"
      data-update-url="{{ $urls['update'] ?? '' }}"
      data-geo-reverse-url="{{ $urls['reverse'] ?? '' }}"
      data-geo-ip-url="{{ $urls['ip'] ?? '' }}">
    @csrf
    <input type="hidden" name="_method" value="POST" data-method-field>
    <x-sf::alert tone="danger" data-form-error hidden>{{ __st('account.fix_fields') }}</x-sf::alert>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-sf::form.input name="full_name" :id="$id.'-full_name'" :label="__('identity::addresses.fields.full_name')" :value="$v('full_name')" autocomplete="name" required maxlength="120" :use-old="false" />
        <x-sf::form.input name="label" :id="$id.'-label'" :label="__('identity::addresses.fields.label')" :value="$v('label')" maxlength="50" :placeholder="__('identity::addresses.label_hint')" :use-old="false" />
        <x-sf::form.phone :id="$id.'-phone'" :label="__('identity::addresses.fields.phone')" :value="$v('phone')" :country="$v('phone_country', $phoneCountry)" required :use-old="false" />
        <x-sf::form.input name="email" type="email" :id="$id.'-email'" :label="__('identity::addresses.fields.email')" :value="$v('email')" autocomplete="email" maxlength="120" dir="ltr" placeholder="name@example.com" :use-old="false" />
    </div>

    <div class="grid justify-items-center gap-3">
        @if (! empty($urls['reverse']))
            <button type="button" data-address-map class="sf-btn sf-btn-accent sf-btn-sm">
                <span class="sf-map-idle inline-flex items-center gap-2"><x-ui::icon name="map" :size="16" />{{ __('identity::addresses.select_on_map') }}<x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></span>
                <span class="sf-map-busy items-center gap-2"><x-ui::icon name="refresh" :size="16" class="mo-spin" />{{ __st('account.detecting') }}</span>
            </button>
            <x-sf::alert tone="warning" class="w-full" data-map-approximate hidden>{{ __st('account.approximate') }}</x-sf::alert>
            <x-sf::alert tone="danger" class="w-full" data-map-error hidden><span data-map-error-text>{{ __st('account.location_failed') }}</span></x-sf::alert>
        @endif
        <p class="flex w-full items-center gap-3 text-sm text-sf-muted"><span class="h-px flex-1 bg-sf-border"></span>{{ __('identity::addresses.or_manual') }}<span class="h-px flex-1 bg-sf-border"></span></p>
    </div>

    <x-sf::form.input name="address_line_1" :id="$id.'-address_line_1'" :label="__('identity::addresses.fields.address_line_1')" :value="$v('address_line_1')" autocomplete="street-address" required maxlength="300" :placeholder="__('identity::addresses.street_hint')" :use-old="false" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-sf::form.field :label="__('identity::addresses.fields.city')" :for="$id.'-city'" required error-key="city">
            <div class="relative" data-city-combobox>
                <div class="sf-control">
                    <input id="{{ $id }}-city" type="text" name="city" value="{{ $v('city') }}" autocomplete="address-level2" required maxlength="100" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-city-list" aria-describedby="{{ $id }}-city-error" data-city-suggestions-url="{{ $urls['cities'] ?? '' }}">
                    <x-ui::icon name="chevron-down" :size="16" class="text-sf-muted" />
                </div>
                <ul id="{{ $id }}-city-list" role="listbox" class="sf-popover" data-city-list></ul>
            </div>
        </x-sf::form.field>
        <x-sf::form.input name="state" :id="$id.'-state'" :label="__('identity::addresses.fields.state')" :value="$v('state')" autocomplete="address-level1" maxlength="100" :use-old="false" />
    </div>

    <x-sf::form.country-select :id="$id.'-country'" :label="__('identity::addresses.fields.country_id')" :countries="$countries" :value="$v('country_id')" required :use-old="false" />

    <input type="hidden" name="latitude" value="{{ $v('latitude') }}">
    <input type="hidden" name="longitude" value="{{ $v('longitude') }}">

    <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
        <x-sf::form.switch name="is_default" :id="$id.'-is_default'" :label="__('identity::addresses.fields.is_default')" :checked="(bool) $v('is_default')" />
        <x-sf::form.button type="submit" :busy="__('identity::addresses.saving')" data-submit-label data-label-save="{{ __('identity::addresses.save') }}" data-label-update="{{ __('identity::addresses.update') }}">{{ $address ? __('identity::addresses.update') : __('identity::addresses.save') }}</x-sf::form.button>
    </div>
</form>
