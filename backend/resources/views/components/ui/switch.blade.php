{{--
    Switch (Figma 28:10233): track 36×20, knob 16, on = brand. With url it updates
    optimistically (PATCH {field: 1|0}), shows the response toast and reverts on failure.
--}}
@props(['name', 'label' => null, 'checked' => false, 'id' => null, 'description' => null, 'disabled' => false, 'url' => null, 'field' => null, 'value' => '1', 'uncheckedValue' => '0'])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $old = \App\Shared\Support\Ui::old($errorKey);
    $isChecked = $old !== null ? in_array((string) $value, array_map('strval', (array) $old), true) : $checked;
    $hasHiddenFallback = ! $url && $uncheckedValue !== null && $uncheckedValue !== false && ! str_ends_with($name, '[]');
@endphp
<label for="{{ $id }}" {{ $attributes->only('class')->class(['inline-flex items-center gap-2.5 text-sm', 'cursor-not-allowed text-ink-muted' => $disabled, 'cursor-pointer text-ink' => ! $disabled]) }}>
    @if ($hasHiddenFallback)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif
    <input
        type="checkbox"
        role="switch"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        class="ui-switch"
        @checked($isChecked)
        @disabled($disabled)
        @if ($url) data-switch-url="{{ $url }}" data-switch-field="{{ $field ?? $name }}" @endif
        {{ $attributes->except('class') }}
    >
    @if ($label || $description)
        <span class="grid gap-0.5">
            <span>{{ $label }}</span>
            @if ($description)
                <span class="text-xs text-ink-secondary">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>
