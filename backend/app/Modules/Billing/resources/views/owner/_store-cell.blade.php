{{-- Owner portal: store name + store address. --}}
@if ($tenant)
    <span class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $tenant->name }}</span>
        <bdi dir="ltr" class="truncate text-xs text-ink-secondary">{{ $tenant->subdomainHost() }}</bdi>
    </span>
@else
    <span class="text-ink-muted">—</span>
@endif
