{{--
    Icon picker (Select2 with the icon + its title in the list and the selected value;
    searchable by title and name). Internal icons (ad-*, tp-*) are left out unless already
    selected. Titles: ui.icons.<name> (ar/en), else the humanised name.
    <x-ui::icon-picker name="icon" :label="…" :selected="$label->icon" :placeholder="…" />
--}}
@props([
    'name',
    'label' => null,
    'selected' => null,
    'placeholder' => null,
    'helper' => null,
    'required' => false,
    'allowClear' => true,
])
@php
    $current = (string) (\App\Shared\Support\Ui::old(\App\Shared\Support\Ui::errorKey($name), $selected) ?? '');
    $iconOptions = \App\Shared\Support\Icon::pickerOptions($current !== '' ? $current : null);
@endphp
<x-ui::select :name="$name" :label="$label" :placeholder="$placeholder" :helper="$helper" :required="$required" :allow-clear="$allowClear" data-icon-picker {{ $attributes }}>
    @foreach ($iconOptions as $option)
        <option value="{{ $option['value'] }}" data-icon-svg="{{ $option['svg'] }}" @selected($option['value'] === $current)>{{ $option['label'] }}</option>
    @endforeach
</x-ui::select>
