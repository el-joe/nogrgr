{{-- Unread vendor messages (count) or a "New" pill for a request the admin never opened. --}}
@if ($count > 0)
    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-2 text-xs font-semibold text-on-primary" title="{{ __('catalog::requests.unread_label', ['count' => $count]) }}">{{ $count }}</span>
@elseif (! $seen)
    <x-ui::badge tone="brand">{{ __('catalog::requests.never_opened') }}</x-ui::badge>
@else
    <span class="text-ink-secondary">0</span>
@endif
