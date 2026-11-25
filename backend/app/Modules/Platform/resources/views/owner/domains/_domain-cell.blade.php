{{-- Domain + store name. --}}
<span class="grid min-w-0">
    <a href="{{ route('owner.domains.show', $request->id) }}" class="truncate font-medium text-ink hover:text-ink-brand" dir="ltr">{{ $request->host }}</a>
    <span class="truncate text-xs text-ink-secondary">{{ $request->getAttribute('store_name') ?? $request->tenant?->name }}</span>
</span>
