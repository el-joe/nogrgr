{{--
    Searchable country select (flag + name, search, empty text, clear ×, flips above when
    there is no room). Progressive enhancement over a native <select name> so the
    contract (country_id) and the data-country-select hook stay the same.
--}}
@props(['name' => 'country_id', 'label' => null, 'countries' => [], 'value' => null, 'id' => null, 'required' => false, 'error' => null, 'placeholder' => null, 'useOld' => true])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= 'sf-'.\App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = (int) ($useOld ? \App\Shared\Support\Ui::old($errorKey, $value) : $value);
    $placeholder ??= __('identity::addresses.choose_country');
@endphp
<x-sf::form.field :label="$label" :for="$id" :required="$required" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div class="relative" data-sf-combobox data-empty-text="{{ __st('shell.no_countries') }}" data-search-text="{{ __st('shell.search_country') }}" data-clear-text="{{ __st('shell.clear') }}">
        <select id="{{ $id }}" name="{{ $name }}" data-country-select class="sf-control" @if ($error) aria-invalid="true" @endif @required($required) aria-describedby="{{ $id }}-error" {{ $attributes->except('class') }}>
            <option value="">{{ $placeholder }}</option>
            @foreach ($countries as $country)
                <option value="{{ $country['id'] }}" data-iso2="{{ $country['iso2'] }}" data-flag="{{ $country['flag'] }}" @selected($current === (int) $country['id'])>{{ $country['name'] }}</option>
            @endforeach
        </select>
    </div>
</x-sf::form.field>
