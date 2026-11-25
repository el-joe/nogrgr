<div class="grid">
    <a href="{{ route('admin.shipping.rules.edit', $rule) }}" class="font-medium text-ink hover:text-ink-brand">{{ $rule->name }}</a>
    <span class="text-xs text-ink-secondary">{{ trans_choice('shipping::rules.zones_count', $rule->zones->count(), ['count' => $rule->zones->count()]) }}</span>
</div>
