<span class="grid gap-0.5">
    <x-ui::badge tone="brand">{{ $subscription->plan->name }}</x-ui::badge>
    <span class="text-xs text-ink-secondary">{{ $subscription->term->label() }}</span>
</span>
