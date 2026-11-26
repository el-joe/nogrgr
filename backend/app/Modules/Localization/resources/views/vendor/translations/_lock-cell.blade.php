{{-- Locked by the platform / edited by the store (kept from AI) / neither. --}}
@if ($platform)
    <x-ui::badge tone="brand" dot>{{ __('localization::vendor_translations.lock.platform') }}</x-ui::badge>
@elseif ($edited)
    <x-ui::badge tone="info" dot>{{ __('localization::vendor_translations.lock.edited') }}</x-ui::badge>
@else
    <x-ui::badge tone="neutral" dot><span aria-hidden="true">—</span><span class="sr-only">{{ __('localization::vendor_translations.lock.none') }}</span></x-ui::badge>
@endif
