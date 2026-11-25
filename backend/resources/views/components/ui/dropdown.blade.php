{{--
    Dropdown / popover menu: 160ms fade + 4px slide, Shadow/MD, radius 10, 8px padding.
    <x-ui::dropdown align="end" width="200">
        <x-slot:trigger><x-ui::icon-button icon="more" :label="…" data-dropdown-trigger /></x-slot:trigger>
        <x-ui::dropdown-item icon="edit" href="…">Edit</x-ui::dropdown-item>
    </x-ui::dropdown>
    The trigger must carry data-dropdown-trigger (icon-button / button pass it through).
--}}
@props(['align' => 'end', 'width' => 200, 'id' => null, 'label' => null, 'position' => 'bottom'])
@php
    $id ??= 'dd-'.\Illuminate\Support\Str::random(8);
@endphp
<div data-dropdown {{ $attributes->class(['relative inline-flex']) }}>
    {{ $trigger }}
    <div
        id="{{ $id }}"
        data-dropdown-menu
        role="menu"
        @if ($label) aria-label="{{ $label }}" @endif
        @class([
            'ui-popover',
            'end-0' => $align === 'end',
            'start-0' => $align === 'start',
            'top-full mt-1' => $position === 'bottom',
            'bottom-full mb-1' => $position === 'top',
        ])
        @style(['width: '.(is_numeric($width) ? $width.'px' : $width)])
    >
        {{ $slot }}
    </div>
</div>
