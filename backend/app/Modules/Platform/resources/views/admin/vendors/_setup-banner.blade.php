{{-- Vendor profile › provisioning banner (pending / provisioning / failed) with Retry. --}}
@php
    $failed = $tenant->status === \App\Modules\Platform\Enums\TenantStatus::Failed;
    $key = $tenant->status->value;
@endphp
<div @class([
    'flex flex-wrap items-start gap-3 rounded-card border p-4 text-sm',
    'border-danger/30 bg-danger-bg' => $failed,
    'border-warning/30 bg-warning-bg' => ! $failed,
]) role="status">
    <x-ui::icon :name="$failed ? 'alert-triangle' : 'clock'" :size="20" @class(['mt-0.5 shrink-0', 'text-danger' => $failed, 'text-warning' => ! $failed]) />
    <div class="grid min-w-0 flex-1 gap-1">
        <p class="font-semibold text-ink">{{ __('platform::vendors.profile.banner.'.$key) }}</p>
        @if ($failed && $tenant->provisioning_error)
            <p class="break-words text-ink-secondary">{{ __('platform::vendors.profile.banner.error', ['error' => $tenant->provisioning_error]) }}</p>
        @elseif (! $failed)
            <p class="text-ink-secondary">{{ __('platform::vendors.profile.banner.'.$key.'_hint') }}</p>
        @endif
        @if ($tenant->provisioning_attempts > 0)
            <p class="text-xs text-ink-muted">{{ __('platform::vendors.profile.banner.attempts', ['count' => \Illuminate\Support\Number::format($tenant->provisioning_attempts, locale: \App\Shared\Support\Locale::numberLocale())]) }}</p>
        @endif
    </div>
    @if ($canRetry && $canManage)
        <x-ui::button
            size="sm"
            icon="refresh"
            :data-confirm="__('platform::vendors.actions.confirm_retry_message')"
            :data-confirm-title="__('platform::vendors.actions.confirm_retry_title', ['name' => $tenant->name])"
            :data-confirm-label="__('platform::vendors.actions.retry')"
            data-confirm-tone="primary"
            data-confirm-method="POST"
            :data-confirm-url="route('admin.vendors.retry', $tenant->id)"
            data-vendor-reload
        >{{ __('platform::vendors.actions.retry') }}</x-ui::button>
    @endif
</div>
