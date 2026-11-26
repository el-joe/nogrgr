{{--
    Vendor › Settings › Roles & permissions (VEN-077, Figma Tenant Panel 90:115822): the
    store's roles with their staff and permission counts; edit (VEN-077a) / duplicate /
    delete. The built-in "owner" role is locked; an assigned role cannot be deleted.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('settings.roles.manage');
@endphp
<x-vendor::layout :title="__('identity::roles.title')" page="vendor-staff">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('identity::roles.title')"
            :subtitle="__('identity::staff.roles.subtitle')"
            :breadcrumbs="[__('navigation.admin.settings') => null, __('identity::roles.title') => null]"
        >
            <x-slot:actions>
                @if (auth('vendor')->user()?->can('settings.staff.view'))
                    <x-ui::button variant="secondary" icon="users" :href="vendor_route('settings.staff.index')">{{ __('identity::staff.title') }}</x-ui::button>
                @endif
                @if ($canManage)
                    <x-ui::button icon="plus" :href="vendor_route('settings.roles.create')">{{ __('identity::roles.add') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-roles"
            :url="vendor_route('settings.roles.data')"
            :columns="[
                ['data' => 'name', 'title' => __('identity::roles.columns.name')],
                ['data' => 'users_count', 'title' => __('identity::staff.roles.columns.staff'), 'searchable' => false],
                ['data' => 'permissions_count', 'title' => __('identity::roles.columns.permissions'), 'searchable' => false],
                ['data' => 'updated_at', 'title' => __('identity::roles.columns.updated'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('identity::roles.empty_title')"
            :empty-description="__('identity::staff.roles.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-roles" :search-placeholder="__('identity::roles.search')" />
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="vendor_route('settings.roles.create')">{{ __('identity::roles.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-vendor::layout>
