{{-- Compliance queue › Store: name + default host (links to the review page). --}}
<a href="{{ route('admin.vendors.compliance.show', $profile->id) }}" class="grid min-w-0 hover:underline">
    <span class="truncate font-medium text-ink">{{ $profile->getAttribute('store_name') }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ \App\Modules\Platform\Support\TenantSlug::subdomainHost((string) $profile->getAttribute('store_slug')) }}</span>
</a>
