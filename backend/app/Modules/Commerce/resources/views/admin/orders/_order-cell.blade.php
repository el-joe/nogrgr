<a href="{{ $url }}" class="block font-semibold text-ink hover:text-ink-brand" dir="ltr">#{{ $fact->order_number }}</a>
<span class="text-xs text-ink-secondary">{{ $fact->placed_at?->translatedFormat('j M Y, H:i') }}</span>
@if ($fact->isOwnProducts())
    <x-ui::badge tone="neutral" class="ms-1">{{ __('commerce::orders_admin.own_badge') }}</x-ui::badge>
@endif
