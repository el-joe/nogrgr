{{-- Store name + host line in request tables. --}}
<div class="grid min-w-0">
    <span class="truncate font-medium text-ink">{{ $name ?? '—' }}</span>
    @if (! empty($slug))
        <bdi dir="ltr" class="truncate text-xs text-ink-secondary">{{ $slug }}.{{ config('tenancy.base_domain') }}</bdi>
    @endif
</div>
