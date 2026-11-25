{{--
    Phone input (intl-tel-input — Figma 27:10346): flag + dial code + searchable country
    list, then the number. The visitor's country (<html data-country>) is pre-selected
    unless :country is given. Submits <name> plus <name>_e164 and <name>_country.
--}}
@props(['name', 'label' => null, 'value' => null, 'id' => null, 'country' => null, 'helper' => null, 'error' => null, 'required' => false, 'disabled' => false])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = \App\Shared\Support\Ui::old($errorKey, $value);
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div class="ui-control ui-phone px-0" dir="ltr">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="tel"
            data-phone
            autocomplete="tel"
            @if ($country) data-country="{{ strtolower($country) }}" @endif
            @if ($current) value="{{ $current }}" @endif
            aria-describedby="{{ $id }}-helper {{ $id }}-error"
            @if ($error) aria-invalid="true" @endif
            @required($required)
            @disabled($disabled)
            {{ $attributes->except('class') }}
        >
    </div>
</x-ui::field>
