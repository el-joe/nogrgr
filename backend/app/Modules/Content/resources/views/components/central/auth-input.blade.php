{{--
    Auth text field (Figma: 48px, radius 8, #C4C4C4 border, label inside + red asterisk)
    with the Laravel $errors message below. password=true adds a show/hide toggle.
--}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => true, 'autocomplete' => null, 'password' => false])
@php
    $id = 'auth-'.$name;
    $error = $errors->first($name);
@endphp
<div class="flex flex-col gap-1.5">
    <div class="site-float" data-field>
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" placeholder=" " @if ($value !== null) value="{{ $value }}" @endif @required($required)
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if (in_array($type, ['email', 'password'], true)) dir="ltr" @endif
            {{ $attributes->class(['site-input h-12 w-full rounded-lg border border-site-border bg-white px-4 pb-1.5 text-sm text-site-ink', 'pe-12' => $password]) }}>
        <label for="{{ $id }}">{{ $label }}@if ($required)<span class="text-danger"> *</span>@endif</label>
        @if ($password)
            <button type="button" class="absolute inset-y-0 end-0 grid w-12 place-items-center text-site-gray hover:text-primary" data-password-toggle aria-pressed="false"
                aria-label="{{ __('content::site.auth.show_password') }}" data-label-show="{{ __('content::site.auth.show_password') }}" data-label-hide="{{ __('content::site.auth.hide_password') }}">
                <x-ui::icon name="eye" :size="18" />
            </button>
        @endif
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert">{{ $error }}</p>
    @endif
</div>
