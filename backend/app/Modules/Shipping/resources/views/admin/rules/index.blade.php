{{--
    Admin › Logistics › Shipping › Rules & zones (ADM-032, Figma Admin Panel 71:43578):
    server-side table (rule + carriers, countries, zones, bands, weight range, status),
    search, country + status filters, edit and delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.rules.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::rules.title')">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('shipping::rules.title')"
            :subtitle="__('shipping::rules.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('navigation.admin.rules_zones') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="route('admin.shipping.rules.create')">{{ __('shipping::rules.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="shipping-rules"
            :url="route('admin.shipping.rules.data')"
            :columns="[
                ['data' => 'name', 'title' => __('shipping::rules.columns.rule')],
                ['data' => 'countries', 'title' => __('shipping::rules.columns.country'), 'orderable' => false, 'searchable' => false],
                ['data' => 'zones_count', 'title' => __('shipping::rules.columns.zones'), 'align' => 'end', 'searchable' => false],
                ['data' => 'bands', 'title' => __('shipping::rules.columns.bands'), 'align' => 'end', 'orderable' => false, 'searchable' => false],
                ['data' => 'range', 'title' => __('shipping::rules.columns.range'), 'orderable' => false, 'searchable' => false],
                ['data' => 'status', 'title' => __('shipping::rules.columns.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('shipping::rules.empty_title')"
            :empty-description="__('shipping::rules.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="shipping-rules" :search-placeholder="__('shipping::rules.search')">
                    <x-ui::select name="country_id" :label="__('shipping::rules.columns.country')" label-hidden :options="$countries" :placeholder="__('shipping::rules.columns.country')" allow-clear data-filter :data-filter-label="__('shipping::rules.columns.country')" class="w-48" />
                    <x-ui::select name="status" :label="__('shipping::rules.columns.status')" label-hidden :options="$statuses" :placeholder="__('shipping::rules.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('shipping::rules.columns.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.shipping.rules.create')">{{ __('shipping::rules.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-layouts.panel>
