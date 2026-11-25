{{--
    Date range picker (Figma Date Picker / Range 27:10490): presets column (Today,
    Last 7 days, Last 30 days, This month, Custom) + month grid; range ends use the
    brand fill. Two hidden inputs: from-name / to-name (default <name>_from / <name>_to).
    filter=true makes it part of a filters bar (URL-synced).
--}}
@props(['name', 'label' => null, 'from' => null, 'to' => null, 'fromName' => null, 'toName' => null, 'id' => null, 'placeholder' => null, 'min' => null, 'max' => null, 'helper' => null, 'error' => null, 'required' => false, 'disabled' => false, 'filter' => false, 'labelHidden' => false, 'compact' => false])
@php
    $fromName ??= $name.'_from';
    $toName ??= $name.'_to';
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null) ?? \App\Shared\Support\Ui::error($fromName, $errors ?? null) ?? \App\Shared\Support\Ui::error($toName, $errors ?? null);
    $placeholderText = $placeholder ?? __('ui.date.range_placeholder');
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" :label-hidden="$labelHidden" {{ $attributes->only('class') }}>
    <div class="relative" data-datepicker data-mode="range" @if ($min) data-min="{{ $min }}" @endif @if ($max) data-max="{{ $max }}" @endif>
        <div @class(['ui-control', 'min-h-10' => $compact])>
            <x-ui::icon name="calendar" :size="18" class="text-ink-secondary" />
            <button type="button" id="{{ $id }}" data-date-trigger @class(['min-w-0 flex-1 truncate text-start outline-none', $compact ? 'h-9' : 'h-10']) aria-haspopup="dialog" aria-expanded="false" aria-describedby="{{ $id }}-helper {{ $id }}-error" @disabled($disabled)>
                <span data-date-display data-placeholder="{{ $placeholderText }}">{{ $placeholderText }}</span>
            </button>
            <button type="button" data-date-clear class="grid size-6 place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink" aria-label="{{ __('ui.date.clear') }}" title="{{ __('ui.date.clear') }}" hidden>
                <x-ui::icon name="x" :size="16" />
            </button>
            <input type="hidden" name="{{ $fromName }}" value="{{ \App\Shared\Support\Ui::old($fromName, $from) }}" data-date-value="from" @if ($filter) data-filter data-filter-label="{{ $label }}" @endif @if ($error) aria-invalid="true" @endif>
            <input type="hidden" name="{{ $toName }}" value="{{ \App\Shared\Support\Ui::old($toName, $to) }}" data-date-value="to" @if ($filter) data-filter @endif>
        </div>
        <div class="ui-popover end-0 top-full mt-1 w-max max-w-[calc(100vw-32px)] sm:start-0 sm:end-auto" data-date-popover role="dialog" aria-label="{{ $label ?? __('ui.date.choose_range') }}"></div>
    </div>
</x-ui::field>
