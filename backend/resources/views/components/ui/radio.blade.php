{{-- Radio (Figma 28:10220): 18px, checked = 5px brand ring. Group several with the same name. --}}
@props(['name', 'value', 'label' => null, 'checked' => false, 'id' => null, 'description' => null, 'disabled' => false])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name, (string) $value);
    $old = \App\Shared\Support\Ui::old($errorKey);
    $isChecked = $old !== null ? (string) $old === (string) $value : $checked;
@endphp
<label for="{{ $id }}" {{ $attributes->only('class')->class(['inline-flex items-start gap-2.5 text-sm', 'cursor-not-allowed text-ink-muted' => $disabled, 'cursor-pointer text-ink' => ! $disabled]) }}>
    <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="ui-check mt-px" @checked($isChecked) @disabled($disabled) {{ $attributes->except('class') }}>
    @if ($label || $description)
        <span class="grid gap-0.5">
            <span>{{ $label }}</span>
            @if ($description)
                <span class="text-xs text-ink-secondary">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>
