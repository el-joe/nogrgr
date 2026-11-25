{{--
    Phone (intl-tel-input): separate dial code, the visitor's country pre-selected, live
    validity tick / cross. Submits `<name>` as E.164 and `<country-field>` (ISO2) —
    hooks data-phone-input / data-phone-country-field kept from the backend contract.
--}}
@props(['name' => 'phone', 'countryField' => 'phone_country', 'label' => null, 'value' => null, 'country' => null, 'id' => null, 'required' => false, 'helper' => null, 'error' => null, 'useOld' => true])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= 'sf-'.\App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = $useOld ? \App\Shared\Support\Ui::old($errorKey, $value) : $value;
    $countryValue = strtoupper((string) ($useOld ? \App\Shared\Support\Ui::old($countryField, $country) : $country) ?: \App\Shared\Support\Locale::visitorCountry());
@endphp
<x-sf::form.field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div class="sf-control sf-phone px-0 pe-3" dir="ltr" data-phone-wrapper>
        <input id="{{ $id }}" type="tel" name="{{ $name }}" @if ($current) value="{{ $current }}" @endif autocomplete="tel" data-phone-input data-phone-country-field="{{ $countryField }}" aria-describedby="{{ $id }}-error" @if ($error) aria-invalid="true" @endif @required($required) {{ $attributes->except('class') }}>
        <span class="sf-phone-valid" role="img" aria-label="{{ __st('shell.phone_valid') }}"><x-ui::icon name="check-circle" :size="18" /></span>
        <span class="sf-phone-invalid" role="img" aria-label="{{ __st('shell.phone_invalid') }}"><x-ui::icon name="x-circle" :size="18" /></span>
    </div>
    <input type="hidden" name="{{ $countryField }}" value="{{ $countryValue }}">
</x-sf::form.field>
