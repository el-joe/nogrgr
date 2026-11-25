{{--
    Field wrapper used by every form control: label (14 Medium, red * when required),
    control slot, helper (12 secondary) and the error slot bound to the 422 contract
    (form-errors.js fills [data-error-for="<dotted.key>"]; the error replaces the helper).
--}}
@props(['label' => null, 'for' => null, 'required' => false, 'helper' => null, 'error' => null, 'errorKey' => null, 'labelHidden' => false])
<div data-field {{ $attributes->class(['grid content-start gap-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif @class(['text-sm font-medium text-ink', 'sr-only' => $labelHidden])>
            {{ $label }}@if ($required)<span class="ms-0.5 text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($helper)
        <p id="{{ $for }}-helper" class="ui-helper text-xs text-ink-secondary">{{ $helper }}</p>
    @endif
    <p id="{{ $for }}-error" data-error-for="{{ $errorKey }}" class="ui-error text-xs text-danger" role="alert" @if (! $error) hidden @endif>{{ $error }}</p>
</div>
