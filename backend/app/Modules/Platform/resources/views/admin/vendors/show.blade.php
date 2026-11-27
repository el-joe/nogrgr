{{--
    Admin › Vendors › Vendor profile (ADM-041 / 041a, Figma 72:49758): header with
    status, subtitle (host · owner · plan · since) and Impersonate / Suspend|Activate /
    Edit store. Tabs (?tab=): Overview, Plan & subscription, Domains, Profit &
    commission, Stats & orders (ADM-041e, Figma 72:51480), Compliance, Mobile app
    (ADM-041g). Limits belong to a later phase and are not rendered.
    Pending / provisioning / failed stores show a banner with the error and Retry.
--}}
@use('App\Modules\Platform\Enums\TenantStatus')
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('vendors.manage');
    $canImpersonate = (bool) $admin?->can('tenants.impersonate') && ($tenant->status->isOperational() || $tenant->status === TenantStatus::Suspended);
    $canActivate = $canManage && $tenant->status->canTransitionTo(TenantStatus::Active);
    $canSuspend = $canManage && $tenant->status->canTransitionTo(TenantStatus::Suspended);
    $planName = $subscription?->plan?->name;
    $subtitle = collect([
        $tenant->subdomainHost(),
        $tenant->owner_name ? __('platform::vendors.profile.owner_line', ['name' => $tenant->owner_name]) : null,
        $planName ? __('platform::vendors.profile.plan_line', ['plan' => $planName]) : null,
        $tenant->created_at ? __('platform::vendors.profile.since', ['date' => $tenant->created_at->translatedFormat('M Y')]) : null,
    ])->filter()->implode(' · ');
    $tabItems = [];
    foreach (\App\Modules\Platform\Http\Controllers\Admin\VendorProfileController::TABS as $key) {
        $tabItems[$key] = array_filter([
            'label' => __('platform::vendors.profile.tabs.'.$key),
            'count' => $key === 'domains' ? $domainCount : null,
            'href' => route('admin.vendors.show', ['tenant' => $tenant->id, 'tab' => $key]),
        ], fn ($value) => $value !== null);
    }
    $inSetup = in_array($tenant->status, [TenantStatus::Pending, TenantStatus::Provisioning, TenantStatus::Failed], true);
@endphp
<x-layouts.panel surface="admin" :title="$tenant->name" data-page="admin-vendors">
    <div class="grid gap-6 *:min-w-0" data-vendor-profile data-open-param="open">
        <x-ui::page-header
            :title="$tenant->name"
            :subtitle="$subtitle"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), $tenant->name => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$tenant->status->tone()">{{ $tenant->status->label() }}</x-ui::badge>
            </x-slot:status>
            <x-slot:actions>
                @if ($canImpersonate)
                    <x-ui::button variant="secondary" icon="eye" data-overlay-open="impersonate">{{ __('platform::vendors.actions.impersonate') }}</x-ui::button>
                @endif
                @if ($canSuspend)
                    <x-ui::button
                        variant="secondary"
                        icon="x"
                        :data-confirm="__('platform::vendors.actions.confirm_suspend_message')"
                        :data-confirm-title="__('platform::vendors.actions.confirm_suspend_title', ['name' => $tenant->name])"
                        :data-confirm-label="__('platform::vendors.actions.suspend')"
                        data-confirm-method="POST"
                        :data-confirm-url="route('admin.vendors.suspend', $tenant->id)"
                        data-vendor-reload
                    >{{ __('platform::vendors.actions.suspend') }}</x-ui::button>
                @endif
                @if ($canActivate)
                    <x-ui::button
                        variant="secondary"
                        icon="check-circle"
                        :data-confirm="__('platform::vendors.actions.confirm_activate_message')"
                        :data-confirm-title="__('platform::vendors.actions.confirm_activate_title', ['name' => $tenant->name])"
                        :data-confirm-label="__('platform::vendors.actions.activate')"
                        data-confirm-tone="primary"
                        data-confirm-method="POST"
                        :data-confirm-url="route('admin.vendors.activate', $tenant->id)"
                        data-vendor-reload
                    >{{ __('platform::vendors.actions.activate') }}</x-ui::button>
                @endif
                @if ($canManage)
                    <x-ui::button icon="edit" data-overlay-open="edit-store">{{ __('platform::vendors.profile.edit_store') }}</x-ui::button>
                @endif
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="vendor-profile-tabs" :active="$tab" :label="__('platform::vendors.profile.tabs_label')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($inSetup)
            @include('platform::admin.vendors._setup-banner')
        @endif

        @include('platform::admin.vendors._tab-'.$tab)
    </div>

    @if ($canManage)
        @include('platform::admin.vendors._edit-store-modal')
    @endif
    @if ($canImpersonate)
        @include('platform::admin.vendors._impersonate-modal')
    @endif
</x-layouts.panel>
