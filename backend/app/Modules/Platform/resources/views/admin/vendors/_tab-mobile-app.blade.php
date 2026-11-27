{{--
    Vendor profile › Mobile app (ADM-041g, Figma Admin Panel 72:51480 tab row): the store's
    app settings (shared api::panel._form, saved in the store's tenant context) and the
    platform Firebase status with a link to Admin › Integrations › Firebase.
    $mobileApp (MobileAppPanelService::page() or null when the store is not operational).
--}}
@php
    $admin = auth('admin')->user();
    $canEdit = (bool) $admin?->can('vendors.manage');
    $canIntegrations = (bool) $admin?->can('integrations.view');
@endphp
@if ($mobileApp === null)
    <x-ui::card>
        <x-ui::empty-state icon="info" :title="__('platform::vendors.profile.tabs.mobile-app')" :description="__('api::panel.admin.unavailable')" />
    </x-ui::card>
@else
    <div class="grid gap-4" data-admin-mobile-app>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-secondary">{{ __('api::panel.admin.tab_intro') }}</p>
            @if ($canEdit)
                <x-ui::button type="submit" form="admin-mobile-app-form" icon="check">{{ __('api::panel.admin.save') }}</x-ui::button>
            @endif
        </div>
        @include('api::panel._form', [
            'page' => $mobileApp,
            'formId' => 'admin-mobile-app-form',
            'action' => route('admin.vendors.mobile-app.update', $tenant->id),
            'canManage' => $canEdit,
            'pushNote' => $mobileApp['pushReady'] ? __('api::panel.push.admin_ready') : __('api::panel.push.admin_missing'),
            'pushLink' => $canIntegrations ? route('admin.integrations.show', 'firebase') : null,
        ])
    </div>
@endif
