{{--
    Empty state (Figma 31:10356): 88px disc, H3, description, one action.
    variant: no-data | no-results | error.
--}}
@props(['variant' => 'no-data', 'title' => null, 'description' => null, 'icon' => null, 'compact' => false])
@php
    $defaults = [
        'no-data' => ['box', 'ui.empty.no_data_title', 'ui.empty.no_data_description'],
        'no-results' => ['search', 'ui.empty.no_results_title', 'ui.empty.no_results_description'],
        'error' => ['alert-triangle', 'ui.empty.error_title', 'ui.empty.error_description'],
    ];
    [$defaultIcon, $defaultTitle, $defaultDescription] = $defaults[$variant] ?? $defaults['no-data'];
    $isError = $variant === 'error';
@endphp
<div data-variant="{{ $variant }}" {{ $attributes->class(['ui-empty mx-auto flex max-w-sm flex-col items-center text-center', 'py-6 gap-2' => $compact, 'py-12 gap-3' => ! $compact]) }} @if ($isError) role="alert" @endif>
    <span @class(['ui-empty-art grid place-items-center rounded-full', 'size-16' => $compact, 'size-[88px]' => ! $compact, 'bg-danger-bg' => $isError, 'bg-primary-subtle' => ! $isError])>
        <span @class(['ui-empty-icon grid place-items-center rounded-full bg-surface shadow-card', 'size-10' => $compact, 'size-12' => ! $compact, 'text-danger' => $isError, 'text-ink-brand' => ! $isError])>
            <x-ui::icon :name="$icon ?? $defaultIcon" :size="22" />
        </span>
    </span>
    <h3 class="ui-empty-title mt-1 text-base font-semibold text-ink">{{ $title ?? __($defaultTitle) }}</h3>
    <p class="ui-empty-text text-sm text-ink-secondary">{{ $description ?? __($defaultDescription) }}</p>
    @if (trim((string) $slot) !== '')
        <div class="mt-2 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
