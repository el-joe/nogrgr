{{--
    Admin › Logistics › Shipping › Companies (ADM-033, Figma Admin Panel 71:45372):
    server-side table (company + contact, countries, default in, tracking URL, active
    switch), search, country + status filters, view / edit / delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.companies.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::companies.title')">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('shipping::companies.title')"
            :subtitle="__('shipping::companies.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::companies.breadcrumb') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="route('admin.shipping.companies.create')">{{ __('shipping::companies.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="shipping-companies"
            :url="route('admin.shipping.companies.data')"
            :columns="[
                ['data' => 'name', 'title' => __('shipping::companies.columns.company')],
                ['data' => 'countries', 'title' => __('shipping::companies.columns.countries'), 'orderable' => false, 'searchable' => false],
                ['data' => 'default_in', 'title' => __('shipping::companies.columns.default_in'), 'orderable' => false, 'searchable' => false],
                ['data' => 'tracking_url_pattern', 'title' => __('shipping::companies.columns.tracking'), 'searchable' => false],
                ['data' => 'is_active', 'title' => __('shipping::companies.columns.active'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('shipping::companies.empty_title')"
            :empty-description="__('shipping::companies.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="shipping-companies" :search-placeholder="__('shipping::companies.search')">
                    <x-ui::select name="country_id" :label="__('shipping::companies.columns.countries')" label-hidden :options="$countries" :placeholder="__('shipping::companies.filters.country')" allow-clear data-filter :data-filter-label="__('shipping::companies.filters.country')" class="w-48" />
                    <x-ui::select name="status" :label="__('shipping::companies.filters.status')" label-hidden :options="['active' => __('shipping::common.on'), 'inactive' => __('shipping::common.off')]" :placeholder="__('shipping::companies.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('shipping::companies.filters.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.shipping.companies.create')">{{ __('shipping::companies.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-layouts.panel>
