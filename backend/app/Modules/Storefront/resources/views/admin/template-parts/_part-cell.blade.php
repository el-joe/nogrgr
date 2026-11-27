{{-- Template part row: preview (object-contain), name, type, default badge. --}}
<div class="flex min-w-0 items-center gap-3">
    <span class="grid h-[52px] w-[86px] shrink-0 place-items-center overflow-hidden rounded-control bg-subtle text-ink-muted">
        @if ($part->preview_url)
            <img src="{{ $part->preview_url }}" alt="{{ $part->name }}" width="86" height="52" loading="lazy" class="size-full object-contain">
        @else
            <x-ui::icon :name="$part->type->icon()" :size="22" />
        @endif
    </span>
    <div class="min-w-0">
        <p class="flex items-center gap-2 text-sm font-medium text-ink">
            <span class="truncate" dir="auto">{{ $part->name }}</span>
            @if ($part->is_default)
                <x-ui::badge tone="brand">{{ __('content::admin_themes.parts.default_badge') }}</x-ui::badge>
            @endif
        </p>
        <p class="mt-0.5 truncate text-xs text-ink-muted">{{ $part->type->label() }}</p>
    </div>
</div>
