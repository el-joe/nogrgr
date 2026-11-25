{{--
    Vendors list › row actions: view, edit (opens the Edit store form on the profile)
    and more (activate / suspend, impersonate, retry provisioning, delete).
--}}
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('vendors.manage');
    $canDelete = (bool) $admin?->can('vendors.delete');
    $canImpersonate = (bool) $admin?->can('tenants.impersonate') && ($tenant->status->isOperational() || $tenant->status === \App\Modules\Platform\Enums\TenantStatus::Suspended);
    $canActivate = $canManage && $tenant->status->canTransitionTo(\App\Modules\Platform\Enums\TenantStatus::Active);
    $canSuspend = $canManage && $tenant->status->canTransitionTo(\App\Modules\Platform\Enums\TenantStatus::Suspended);
    $canRetry = $canManage && app(\App\Modules\Platform\Services\TenantProvisioningService::class)->canRun($tenant);
    $hasMore = $canActivate || $canSuspend || $canRetry || $canImpersonate || $canDelete;
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.vendors.show', $tenant->id)" :label="__('platform::vendors.actions.view', ['name' => $tenant->name])" />
    @if ($canManage)
        <x-ui::icon-button icon="edit" size="sm" :href="route('admin.vendors.show', ['tenant' => $tenant->id, 'open' => 'edit-store'])" :label="__('platform::vendors.actions.edit', ['name' => $tenant->name])" />
    @endif
    @if ($hasMore)
        <x-ui::row-actions :label="__('platform::vendors.actions.more', ['name' => $tenant->name])">
            @if ($canImpersonate)
                <x-ui::dropdown-item icon="eye" :href="route('admin.vendors.show', ['tenant' => $tenant->id, 'open' => 'impersonate'])">{{ __('platform::vendors.actions.impersonate') }}</x-ui::dropdown-item>
            @endif
            @if ($canActivate)
                <x-ui::dropdown-item
                    icon="check-circle"
                    :data-confirm="__('platform::vendors.actions.confirm_activate_message')"
                    :data-confirm-title="__('platform::vendors.actions.confirm_activate_title', ['name' => $tenant->name])"
                    :data-confirm-label="__('platform::vendors.actions.activate')"
                    data-confirm-tone="primary"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.vendors.activate', $tenant->id)"
                >{{ __('platform::vendors.actions.activate') }}</x-ui::dropdown-item>
            @endif
            @if ($canRetry)
                <x-ui::dropdown-item
                    icon="refresh"
                    :data-confirm="__('platform::vendors.actions.confirm_retry_message')"
                    :data-confirm-title="__('platform::vendors.actions.confirm_retry_title', ['name' => $tenant->name])"
                    :data-confirm-label="__('platform::vendors.actions.retry')"
                    data-confirm-tone="primary"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.vendors.retry', $tenant->id)"
                >{{ __('platform::vendors.actions.retry') }}</x-ui::dropdown-item>
            @endif
            @if ($canSuspend)
                <x-ui::dropdown-item
                    icon="x-circle"
                    tone="danger"
                    :data-confirm="__('platform::vendors.actions.confirm_suspend_message')"
                    :data-confirm-title="__('platform::vendors.actions.confirm_suspend_title', ['name' => $tenant->name])"
                    :data-confirm-label="__('platform::vendors.actions.suspend')"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.vendors.suspend', $tenant->id)"
                >{{ __('platform::vendors.actions.suspend') }}</x-ui::dropdown-item>
            @endif
            @if ($canDelete)
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item
                    icon="trash"
                    tone="danger"
                    :data-confirm="__('platform::vendors.actions.confirm_delete_message')"
                    :data-confirm-title="__('platform::vendors.actions.confirm_delete_title', ['name' => $tenant->name])"
                    :data-confirm-label="__('platform::vendors.actions.delete')"
                    :data-confirm-require="$tenant->name"
                    data-confirm-method="DELETE"
                    :data-confirm-url="route('admin.vendors.destroy', $tenant->id)"
                >{{ __('platform::vendors.actions.delete') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
