<div class="flex items-center gap-3">
    @if ($company->logoUrl())
        <img src="{{ $company->logoUrl() }}" alt="" class="size-8 shrink-0 rounded-control border border-line object-contain" loading="lazy">
    @else
        <span class="grid size-8 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="truck" :size="16" /></span>
    @endif
    <div class="grid min-w-0">
        <a href="{{ route('admin.shipping.companies.show', $company) }}" class="truncate font-medium text-ink hover:text-ink-brand">{{ $company->name }}</a>
        @if ($company->contact_email)
            <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $company->contact_email }}</span>
        @endif
    </div>
</div>
