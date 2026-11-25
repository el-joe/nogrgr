{{--
    Date picker, single date (Figma Date Picker 27:10490). Stores YYYY-MM-DD in a
    hidden input; shows "24 Nov 2026" in the page locale.
--}}
@props(['name', 'label' => null, 'value' => null, 'id' => null, 'placeholder' => null, 'min' => null, 'max' => null, 'helper' => null, 'error' => null, 'required' => false, 'disabled' => false, 'filter' => false])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $current = \App\Shared\Support\Ui::old($errorKey, $value);
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div class="relative" data-datepicker data-mode="single" @if ($min) data-min="{{ $min }}" @endif @if ($max) data-max="{{ $max }}" @endif>
        <div class="ui-control">
            <x-ui::icon name="calendar" :size="18" class="text-ink-secondary" />
            <button type="button" id="{{ $id }}" data-date-trigger class="h-10 min-w-0 flex-1 truncate text-start outline-none" aria-haspopup="dialog" aria-expanded="false" aria-describedby="{{ $id }}-helper {{ $id }}-error" @disabled($disabled)>
                <span data-date-display data-placeholder="{{ $placeholder ?? __('ui.date.placeholder') }}">{{ $placeholder ?? __('ui.date.placeholder') }}</span>
            </button>
            <input type="hidden" name="{{ $name }}" value="{{ $current }}" data-date-value="single" @if ($filter) data-filter data-filter-label="{{ $label }}" @endif @if ($error) aria-invalid="true" @endif {{ $attributes->except('class') }}>
        </div>
        <div class="ui-popover start-0 top-full mt-1 w-max" data-date-popover role="dialog" aria-label="{{ $label ?? __('ui.date.choose') }}"></div>
    </div>
</x-ui::field>
