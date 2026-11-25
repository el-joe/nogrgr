{{--
    Textarea (Figma 27:10512): min 112h, padding 10×12, vertical resize. With maxlength
    a live "n / max" counter sits in the helper slot.
--}}
@props(['name', 'label' => null, 'value' => null, 'id' => null, 'helper' => null, 'error' => null, 'required' => false, 'disabled' => false, 'maxlength' => null, 'rows' => 4])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = (string) \App\Shared\Support\Ui::old($errorKey, $value);
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="ui-control"
        aria-describedby="{{ $id }}-helper {{ $id }}-error"
        @if ($maxlength) maxlength="{{ $maxlength }}" data-counter="#{{ $id }}-count" @endif
        @if ($error) aria-invalid="true" @endif
        @required($required)
        @disabled($disabled)
        {{ $attributes->except('class') }}
    >{{ $current }}</textarea>
    @if ($helper || $maxlength)
        <div id="{{ $id }}-helper" class="ui-helper flex justify-between gap-3 text-xs text-ink-secondary">
            <span>{{ $helper }}</span>
            @if ($maxlength)
                <span id="{{ $id }}-count" dir="ltr" aria-live="polite">{{ mb_strlen($current) }} / {{ $maxlength }}</span>
            @endif
        </div>
    @endif
</x-ui::field>
