{{-- A text in one language (shown in its own direction); "—" when empty. --}}
@if ($value === null || $value === '')
    <span class="text-ink-muted">—</span>
@else
    <span class="line-clamp-2 max-w-[360px] text-ink" lang="{{ $locale }}" dir="auto">{{ $value }}</span>
@endif
