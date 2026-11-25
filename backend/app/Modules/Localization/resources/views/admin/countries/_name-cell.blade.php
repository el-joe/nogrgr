<div class="flex items-center gap-3">
    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-subtle text-base" aria-hidden="true">{{ $country->flag }}</span>
    <span class="grid min-w-0">
        <span class="font-medium text-ink">{{ $country->name }}</span>
        <span class="text-xs text-ink-secondary"><bdi dir="ltr">{{ $country->iso2 }} · {{ $country->iso3 }}</bdi></span>
    </span>
</div>
