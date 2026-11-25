<span class="flex min-w-0 items-center gap-3">
    <span class="grid size-9 shrink-0 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon :name="$plan->icon ?: 'star'" :size="18" />
    </span>
    <span class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $plan->name }}</span>
        @if ($plan->description)
            <span class="truncate text-xs text-ink-secondary">{{ $plan->description }}</span>
        @endif
    </span>
</span>
