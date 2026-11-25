{{-- Domain requests › Store: name + default sub-domain host. --}}
<span class="grid min-w-0">
    <span class="truncate font-medium text-ink">{{ $request->getAttribute('store_name') ?? $request->tenant?->name }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ \App\Modules\Platform\Support\TenantSlug::subdomainHost((string) ($request->getAttribute('store_slug') ?? $request->tenant?->slug)) }}</span>
</span>
