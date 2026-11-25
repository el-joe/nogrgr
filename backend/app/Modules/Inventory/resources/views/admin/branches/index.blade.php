{{--
    Admin › Logistics › Branches (ADM-030, Figma Admin Panel 71:40880): server-side table
    (branch, country, SKUs in stock, units, default-for countries, status), search,
    country + status filters, edit and delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.branches.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('inventory::branches.title')">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('inventory::branches.title')"
            :subtitle="__('inventory::branches.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('inventory::branches.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="route('admin.shipping.branches.create')">{{ __('inventory::branches.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="branches"
            :url="route('admin.shipping.branches.data')"
            :columns="[
                ['data' => 'name', 'title' => __('inventory::branches.columns.branch')],
                ['data' => 'country', 'title' => __('inventory::branches.columns.country'), 'searchable' => false],
                ['data' => 'skus_count', 'title' => __('inventory::branches.columns.skus'), 'align' => 'end', 'searchable' => false],
                ['data' => 'units_sum', 'title' => __('inventory::branches.columns.units'), 'align' => 'end', 'searchable' => false],
                ['data' => 'default_for', 'title' => __('inventory::branches.columns.default_for'), 'orderable' => false, 'searchable' => false],
                ['data' => 'is_active', 'title' => __('inventory::branches.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('inventory::branches.empty_title')"
            :empty-description="__('inventory::branches.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="branches" :search-placeholder="__('inventory::branches.search')">
                    <x-ui::select name="country_id" :label="__('inventory::branches.columns.country')" label-hidden :options="$countries" :placeholder="__('inventory::branches.columns.country')" allow-clear data-filter :data-filter-label="__('inventory::branches.columns.country')" class="w-48" />
                    <x-ui::select name="status" :label="__('inventory::branches.columns.status')" label-hidden :options="['active' => __('inventory::branches.active'), 'inactive' => __('inventory::branches.inactive')]" :placeholder="__('inventory::branches.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('inventory::branches.columns.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.shipping.branches.create')">{{ __('inventory::branches.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-layouts.panel>
