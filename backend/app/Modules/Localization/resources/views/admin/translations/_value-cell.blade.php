@php
    $direction = \App\Shared\Support\Locale::direction($locale);
    $status ??= null;
@endphp
@if ($value === null || $value === '')
    <x-ui::badge tone="warning">{{ __('localization::translations.missing_value') }}</x-ui::badge>
@else
    <div class="flex min-w-0 items-center gap-2">
        <span class="line-clamp-2 max-w-md text-ink" dir="{{ $direction }}" lang="{{ $locale }}">{{ $value }}</span>
        @if ($status === \App\Modules\Localization\Enums\TranslationKeyStatus::Overridden)
            <x-ui::badge tone="info">{{ __('localization::translations.edited') }}</x-ui::badge>
        @endif
    </div>
@endif
