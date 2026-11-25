{{--
    Admin › System › Admins (ADM-110, Figma Admin Panel 80:90095): Admins | Roles tabs,
    "Invite admin", server-side table with search + role + status filters, active
    switch, edit (modal, ADM-110a 80:98485) and delete. The modal body is loaded from
    admin.system.admins.create|edit.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('admins.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('identity::admins.title')" data-page="admin-admins">
    <x-ui::page-header
        :title="__('identity::admins.title')"
        :subtitle="__('identity::admins.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('identity::admins.breadcrumb') => null, __('identity::admins.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" data-remote-modal="admin-modal" :data-url="route('admin.system.admins.create')">{{ __('identity::admins.add') }}</x-ui::button>
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            @include('identity::admin._team-tabs', ['active' => 'admins', 'adminsCount' => $counts['all']])
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="admins"
        :url="route('admin.system.admins.data')"
        :columns="[
            ['data' => 'admin', 'name' => 'name', 'title' => __('identity::admins.columns.admin')],
            ['data' => 'role', 'title' => __('identity::admins.columns.role'), 'orderable' => false],
            ['data' => 'last_login_at', 'title' => __('identity::admins.columns.last_active')],
            ['data' => 'status', 'title' => __('identity::admins.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('identity::admins.empty_title')"
        :empty-description="__('identity::admins.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="admins" :search-placeholder="__('identity::admins.search')">
                <x-ui::select name="role" :label="__('identity::admins.columns.role')" label-hidden :options="$roleOptions" :placeholder="__('identity::admins.columns.role')" allow-clear data-filter :data-filter-label="__('identity::admins.columns.role')" class="w-44" />
                <x-ui::select name="status" :label="__('identity::admins.columns.status')" label-hidden :options="\App\Modules\Identity\Enums\AccountStatus::options()" :placeholder="__('identity::admins.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('identity::admins.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" data-remote-modal="admin-modal" :data-url="route('admin.system.admins.create')">{{ __('identity::admins.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>

    @if ($canManage)
        <x-ui::modal id="admin-modal" :title="__('identity::admins.create_title')" size="sm">
            @include('identity::admin._remote-modal-states', ['error' => __('identity::admins.load_error')])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="admin-form" icon="check" data-remote-submit>{{ __('identity::admins.actions.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
