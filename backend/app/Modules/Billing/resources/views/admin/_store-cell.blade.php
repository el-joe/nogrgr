{{-- Store name + slug (tenant may be missing on payment logs). --}}
@if ($tenant)
    <span class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $tenant->name }}</span>
        <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->slug }}</span>
    </span>
@else
    <span class="text-ink-muted">—</span>
@endif
