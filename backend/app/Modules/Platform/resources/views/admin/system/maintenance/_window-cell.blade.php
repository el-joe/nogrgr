{{-- Maintenance windows › window cell: title + internal note. --}}
<div class="min-w-0">
    <p class="font-medium text-ink">{{ $window->title }}</p>
    @if ($window->notes)
        <p class="truncate text-xs text-ink-secondary">{{ $window->notes }}</p>
    @endif
</div>
