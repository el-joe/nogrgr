{{-- Subscriber status pill: subscribed (success) or unsubscribed with its date. --}}
@if ($subscriber->unsubscribed_at === null)
    <x-ui::badge tone="success">{{ __('engagement::subscribers.statuses.subscribed') }}</x-ui::badge>
@else
    <x-ui::badge :title="$subscriber->unsubscribed_at->translatedFormat('d M Y')">{{ __('engagement::subscribers.statuses.unsubscribed') }}</x-ui::badge>
@endif
