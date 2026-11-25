<div class="flex items-center gap-3">
    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-subtle text-base" aria-hidden="true">{{ $language->flagEmoji() ?? mb_strtoupper(mb_substr($language->code, 0, 2)) }}</span>
    <span class="grid min-w-0">
        <span class="font-medium text-ink">{{ $language->name }}</span>
        <span class="text-xs text-ink-secondary"><bdi dir="{{ $language->direction->value }}">{{ $language->native_name }}</bdi> · <bdi dir="ltr">{{ $language->code }}</bdi></span>
    </span>
</div>
