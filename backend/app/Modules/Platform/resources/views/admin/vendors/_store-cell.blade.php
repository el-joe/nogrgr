{{-- Vendors list › Store: name + default host (links to the profile). --}}
<a href="{{ route('admin.vendors.show', $tenant->id) }}" class="grid min-w-0 hover:underline">
    <span class="truncate font-medium text-ink">{{ $tenant->name }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->subdomainHost() }}</span>
</a>
