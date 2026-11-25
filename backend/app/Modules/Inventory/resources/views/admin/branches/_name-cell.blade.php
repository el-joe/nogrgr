<span class="grid">
    <span class="font-medium text-ink">{{ $branch->name }}</span>
    <span class="text-xs text-ink-secondary">{{ collect([$branch->code, $branch->address, $branch->city?->name])->filter()->implode(' · ') }}</span>
</span>
