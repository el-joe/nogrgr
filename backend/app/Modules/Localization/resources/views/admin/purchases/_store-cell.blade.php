@php($tenant = $purchase->tenant)
<div class="grid min-w-0 gap-0.5">
    <span class="truncate font-medium text-ink">{{ $tenant?->getAttribute('name') ?? '—' }}</span>
    @if ($tenant)
        <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->subdomainHost() }}</span>
    @endif
</div>
