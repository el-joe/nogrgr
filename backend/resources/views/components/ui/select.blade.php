{{--
    Select (Select2 — Figma 27:10284): searchable dropdown, check on the selected option,
    multi = removable brand-subtle tags. Remote options with ajax-url.
    <x-ui::select name="category_id" :options="$categories" :selected="$id" placeholder="…" />
--}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'id' => null,
    'placeholder' => null,
    'multiple' => false,
    'helper' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'ajaxUrl' => null,
    'search' => true,
    'allowClear' => false,
    'labelHidden' => false,
])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = collect((array) \App\Shared\Support\Ui::old($errorKey, $selected))->map(fn ($value) => (string) $value)->all();
    $fieldName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" :label-hidden="$labelHidden" {{ $attributes->only('class') }}>
    <select
        id="{{ $id }}"
        name="{{ $fieldName }}"
        data-select
        class="ui-control"
        @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif
        @if ($ajaxUrl) data-ajax-url="{{ $ajaxUrl }}" @endif
        @if (! $search) data-search="false" @endif
        @if ($allowClear) data-allow-clear="true" @endif
        aria-describedby="{{ $id }}-helper {{ $id }}-error"
        @if ($error) aria-invalid="true" @endif
        @required($required)
        @disabled($disabled)
        @if ($multiple) multiple @endif
        {{ $attributes->except('class') }}
    >
        @if ($placeholder && ! $multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $current, true))>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-ui::field>
