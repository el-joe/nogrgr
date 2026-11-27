{{-- Maintenance windows › scope pill (+ store name for single-store windows). --}}
<div class="flex flex-wrap items-center gap-2">
    <x-ui::badge :tone="$window->scope->tone()">{{ $window->scope->label() }}</x-ui::badge>
    @if ($window->scope === \App\Modules\Platform\Enums\MaintenanceScope::Store)
        <span class="text-xs text-ink-secondary">{{ $window->tenant?->name ?? '#'.$window->tenant_id }}</span>
    @endif
</div>
