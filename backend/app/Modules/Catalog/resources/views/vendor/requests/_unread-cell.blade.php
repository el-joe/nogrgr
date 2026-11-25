{{-- Unread platform replies of the store (count pill) or a dash. --}}
@if ($count > 0)
    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-2 text-xs font-semibold text-on-primary" title="{{ trans_choice('catalog::vendor_requests.unread', $count, ['count' => $count]) }}">{{ $count }}</span>
@else
    <span class="text-ink-secondary">—</span>
@endif
