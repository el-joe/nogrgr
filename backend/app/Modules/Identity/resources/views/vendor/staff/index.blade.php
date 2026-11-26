{{--
    Vendor › Settings › Staff (VEN-076, Figma Tenant Panel 90:115176): store team list
    (member, role, status, last sign-in) with search + role / status filters, the plan's
    seat usage, invite (VEN-076a modal), view, edit, activate / deactivate, resend the
    invitation and delete. Owners are untouchable and nobody acts on their own row.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('settings.staff.manage');
@endphp
<x-vendor::layout :title="__('identity::staff.title')" page="vendor-staff">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('identity::staff.title')"
            :subtitle="__('identity::staff.subtitle')"
            :breadcrumbs="[__('navigation.admin.settings') => null, __('identity::staff.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    @if (auth('vendor')->user()?->can('settings.roles.view'))
                        <x-ui::button variant="secondary" icon="key" :href="vendor_route('settings.roles.index')">{{ __('identity::staff.manage_roles') }}</x-ui::button>
                    @endif
                    @if ($limitReached)
                        <x-ui::button icon="plus" disabled aria-disabled="true" :title="trans_choice('identity::staff.rules.limit', $limit, ['count' => $limit])" data-live="staff-invite">{{ __('identity::staff.invite') }}</x-ui::button>
                    @else
                        <x-ui::button icon="plus" data-remote-modal="vendor-staff-modal" :data-url="vendor_route('settings.staff.create')" data-live="staff-invite">{{ __('identity::staff.invite') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        {{-- Live regions (QA-P8-005): counts, seats and the limit notice refresh after invite / toggle / delete. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-ink-secondary" data-live="staff-counts">
            <span>{{ trans_choice('identity::staff.counts.all', $counts['all'], ['count' => number_format($counts['all'])]) }}</span>
            <span>{{ trans_choice('identity::staff.counts.active', $counts['active'], ['count' => number_format($counts['active'])]) }}</span>
            <span>{{ trans_choice('identity::staff.counts.pending', $counts['pending'], ['count' => number_format($counts['pending'])]) }}</span>
            <span class="font-medium {{ $limitReached ? 'text-danger' : 'text-ink' }}">
                {{ $limit !== null ? __('identity::staff.seats', ['used' => number_format($counts['all']), 'limit' => number_format($limit)]) : __('identity::staff.seats_unlimited') }}
            </span>
        </div>

        <div class="contents" data-live="staff-limit">
        @if ($limitReached && $canManage)
            <p class="flex items-start gap-2 rounded-card bg-warning-bg px-4 py-3 text-[13px] text-warning">
                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                <span>
                    {{ trans_choice('identity::staff.rules.limit', $limit, ['count' => $limit]) }}
                    @if (auth('vendor')->user()?->can('finance.subscription.view'))
                        <a class="font-medium underline" href="{{ vendor_route('finance.subscription') }}">{{ __('identity::staff.upgrade') }}</a>
                    @endif
                </span>
            </p>
        @endif
        </div>

        <x-ui::datatable
            id="vendor-staff"
            :url="vendor_route('settings.staff.data')"
            :columns="[
                ['data' => 'member', 'title' => __('identity::staff.columns.member')],
                ['data' => 'role', 'title' => __('identity::staff.columns.role'), 'orderable' => false],
                ['data' => 'status', 'title' => __('identity::staff.columns.status')],
                ['data' => 'last_login_at', 'title' => __('identity::staff.columns.last_login')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('identity::staff.empty_title')"
            :empty-description="__('identity::staff.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-staff" :search-placeholder="__('identity::staff.search')">
                    <x-ui::select name="role" :label="__('identity::staff.filters.role')" label-hidden :options="$roleOptions" :placeholder="__('identity::staff.filters.role')" allow-clear data-filter :data-filter-label="__('identity::staff.filters.role')" class="w-44" />
                    <x-ui::select name="status" :label="__('identity::staff.filters.status')" label-hidden :options="$statuses" :placeholder="__('identity::staff.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('identity::staff.filters.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage && ! $limitReached)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="vendor-staff-modal" :data-url="vendor_route('settings.staff.create')">{{ __('identity::staff.invite') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @if ($canManage)
        @include('identity::vendor.staff._modal', ['id' => 'vendor-staff-modal', 'title' => __('identity::staff.invite_title'), 'form' => 'vendor-staff-form'])
    @endif
    @include('identity::vendor.staff._modal', ['id' => 'vendor-staff-view-modal', 'title' => __('identity::staff.title'), 'form' => null])
</x-vendor::layout>
