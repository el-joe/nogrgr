{{-- Field wrapper: label (+ red * when required), control, helper, 422 error slot ([data-error-for]). --}}
@props(['label' => null, 'for' => null, 'required' => false, 'helper' => null, 'error' => null, 'errorKey' => null])
<div data-sf-field data-field {{ $attributes->class(['grid content-start gap-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="sf-label">{{ $label }}@if ($required)<span class="sf-required" aria-hidden="true">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($helper)
        <p id="{{ $for }}-helper" class="sf-helper">{{ $helper }}</p>
    @endif
    <p id="{{ $for }}-error" data-error-for="{{ $errorKey }}" class="sf-error" role="alert" @if (! $error) hidden @endif>{{ $error }}</p>
</div>
