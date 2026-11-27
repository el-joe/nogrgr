{{-- Cache tools › one action: icon, label + hint, last run, button (confirm dialog → POST clear). --}}
@php
    $canManage = (bool) auth('admin')->user()?->can('system.cache.manage');
    $label = __('platform::cache_tools.actions.'.$action->value.'.label');
    $query = array_filter(['action' => $action->value, 'tenant_id' => $tenant?->id]);
@endphp
<li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0" data-cache-action="{{ $action->value }}">
    <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary" aria-hidden="true"><x-ui::icon :name="$action->icon()" :size="18" /></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-ink">{{ $label }}</p>
        <p class="text-xs text-ink-secondary">{{ __('platform::cache_tools.actions.'.$action->value.'.hint') }}</p>
        @include('platform::admin.system.cache._last-run', ['run' => $run, 'key' => $action->value])
    </div>
    @if ($canManage)
        <x-ui::button
            variant="ghost"
            size="sm"
            :data-confirm="__('platform::cache_tools.confirm.message')"
            :data-confirm-title="$tenant ? __('platform::cache_tools.confirm.store_title', ['cache' => $label, 'store' => $tenant->name]) : __('platform::cache_tools.confirm.title', ['cache' => $label])"
            :data-confirm-label="__('platform::cache_tools.actions.'.$action->value.'.button')"
            data-confirm-method="POST"
            :data-confirm-url="route('admin.system.cache.clear', $query)"
        >{{ __('platform::cache_tools.actions.'.$action->value.'.button') }}</x-ui::button>
    @endif
</li>
