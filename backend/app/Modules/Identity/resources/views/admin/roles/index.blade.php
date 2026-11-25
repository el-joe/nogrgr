{{--
    Admin › System › Roles (ADM-111, Figma Admin Panel 80:91028 tabs): roles with their
    admins and permission counts; edit / duplicate / delete. super-admin is locked and
    a role still assigned to admins cannot be deleted.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('roles.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('identity::roles.title')" data-page="admin-roles">
    <x-ui::page-header
        :title="__('identity::roles.title')"
        :subtitle="__('identity::roles.list_subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('identity::admins.breadcrumb') => null, __('identity::roles.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.system.roles.create')">{{ __('identity::roles.add') }}</x-ui::button>
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            @include('identity::admin._team-tabs', ['active' => 'roles'])
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="roles"
        :url="route('admin.system.roles.data')"
        :columns="[
            ['data' => 'name', 'title' => __('identity::roles.columns.name')],
            ['data' => 'users_count', 'title' => __('identity::roles.columns.admins'), 'searchable' => false],
            ['data' => 'permissions_count', 'title' => __('identity::roles.columns.permissions'), 'searchable' => false],
            ['data' => 'updated_at', 'title' => __('identity::roles.columns.updated'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('identity::roles.empty_title')"
        :empty-description="__('identity::roles.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="roles" :search-placeholder="__('identity::roles.search')" />
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.system.roles.create')">{{ __('identity::roles.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
