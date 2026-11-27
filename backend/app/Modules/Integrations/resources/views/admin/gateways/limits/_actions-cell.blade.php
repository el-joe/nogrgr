{{-- Unblock a store that reached its gateway limit this month (ADM-100c). Data: $usage, $canManage. --}}
@if ($canManage && $usage->blocked_at)
    <x-ui::button size="sm" variant="secondary" :href="route('admin.integrations.gateways.limits.unblock', $usage->id)" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('integrations::admin.limits.unblock')" :data-confirm="__('integrations::admin.limits.unblock_confirm', ['store' => (string) ($usage->tenant?->name ?? '—')])">{{ __('integrations::admin.limits.unblock') }}</x-ui::button>
@else
    <span class="text-ink-muted" aria-hidden="true">—</span>
@endif
