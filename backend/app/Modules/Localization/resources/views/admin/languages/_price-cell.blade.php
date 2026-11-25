@if ($language->isCore())
    <span>{{ __('localization::admin.languages.free') }} <span class="text-ink-secondary">({{ $language->is_default ? __('localization::admin.languages.default') : __('localization::admin.languages.core') }})</span></span>
@elseif ($language->price_minor > 0)
    <bdi dir="ltr" class="font-medium tabular-nums">{{ $language->price()->format() }}</bdi>
@else
    <x-ui::badge tone="warning" dot>{{ __('localization::admin.languages.no_price') }}</x-ui::badge>
@endif
