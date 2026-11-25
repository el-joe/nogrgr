{{-- Radio group: fieldset + legend + options (value => label) + 422 error slot. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'error' => null, 'inline' => true])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
@endphp
<fieldset data-field {{ $attributes->class(['grid gap-2']) }} @if ($error) aria-invalid="true" @endif>
    @if ($label)
        <legend class="mb-2 text-sm font-medium text-ink">{{ $label }}@if ($required)<span class="ms-0.5 text-danger" aria-hidden="true">*</span>@endif</legend>
    @endif
    <div @class(['flex flex-wrap gap-x-6 gap-y-2' => $inline, 'grid gap-2' => ! $inline])>
        @foreach ($options as $optionValue => $optionLabel)
            <x-ui::radio :name="$name" :value="$optionValue" :label="$optionLabel" :checked="(string) $value === (string) $optionValue" />
        @endforeach
    </div>
    <p data-error-for="{{ $errorKey }}" class="ui-error text-xs text-danger" role="alert" @if (! $error) hidden @endif>{{ $error }}</p>
</fieldset>
