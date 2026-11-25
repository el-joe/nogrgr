{{--
    Checkbox (Figma 28:10210): 18px, radius 5, checked = brand fill + white glyph;
    indeterminate supported. unchecked-value adds a hidden input so "off" is submitted.
--}}
@props(['name', 'label' => null, 'value' => '1', 'checked' => false, 'id' => null, 'description' => null, 'disabled' => false, 'indeterminate' => false, 'uncheckedValue' => null, 'error' => null])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name, is_scalar($value) && str_ends_with($name, '[]') ? (string) $value : null);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $old = \App\Shared\Support\Ui::old($errorKey);
    $isChecked = $old !== null ? in_array((string) $value, array_map('strval', (array) $old), true) : $checked;
@endphp
<div data-field {{ $attributes->only('class')->class(['grid gap-1']) }}>
    <label for="{{ $id }}" @class(['inline-flex items-start gap-2.5 text-sm', 'cursor-not-allowed text-ink-muted' => $disabled, 'cursor-pointer text-ink' => ! $disabled])>
        @if ($uncheckedValue !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
        @endif
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            class="ui-check mt-px"
            @checked($isChecked)
            @disabled($disabled)
            @if ($indeterminate) data-indeterminate aria-checked="mixed" @endif
            @if ($error) aria-invalid="true" @endif
            aria-describedby="{{ $id }}-error"
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
    <p id="{{ $id }}-error" data-error-for="{{ $errorKey }}" class="ui-error text-xs text-danger" role="alert" @if (! $error) hidden @endif>{{ $error }}</p>
</div>
