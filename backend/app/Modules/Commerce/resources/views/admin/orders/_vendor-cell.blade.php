@php $tenant = $fact->tenant; @endphp
<span class="block font-medium text-ink">{{ $tenant?->getAttribute('name') ?? '—' }}</span>
@if ($tenant && method_exists($tenant, 'subdomainHost'))
    <span class="text-xs text-ink-secondary" dir="ltr">{{ $tenant->subdomainHost() }}</span>
@endif
