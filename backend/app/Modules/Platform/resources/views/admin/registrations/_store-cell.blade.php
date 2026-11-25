{{-- Pending registrations › Store: chosen name + slug (empty until the store step). --}}
@if ($registration->store_name || $registration->slug)
    <span class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $registration->store_name ?? '—' }}</span>
        <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $registration->slug ?? '' }}</span>
    </span>
@else
    <span class="text-ink-secondary">{{ __('platform::registrations.no_store_yet') }}</span>
@endif
