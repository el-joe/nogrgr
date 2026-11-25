{{-- Vendors list › Owner: name + e-mail. --}}
<span class="grid min-w-0">
    <span class="truncate font-medium text-ink">{{ $tenant->owner_name ?? '—' }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->owner_email ?? '' }}</span>
</span>
