{{--
    Vendor › Customers (VEN-025, Figma Tenant Panel 12:15544, mobile 12:23838): KPI cards
    (customers, active, buyers, average lifetime value), customer list (ID, customer,
    contact, orders, status, lifetime value, last order) with search + status / orders /
    joined filters, the CSV report, add (VEN-025a modal), view (VEN-025b), edit and delete.
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('customers.manage');
@endphp
<x-vendor::layout :title="__('identity::customers.title')" page="vendor-customers">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('identity::customers.title')" :subtitle="__('identity::customers.subtitle')">
            @if ($canManage || $staff?->can('customers.export'))
                <x-slot:actions>
                    @if ($staff?->can('customers.export'))
                        <x-ui::button variant="secondary" icon="download" :href="vendor_route('sales.customers.export')">{{ __('identity::customers.report') }}</x-ui::button>
                    @endif
                    @if ($canManage)
                        <x-ui::button icon="plus" data-remote-modal="vendor-customer-modal" :data-url="vendor_route('sales.customers.create')">{{ __('identity::customers.add') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card :label="__('identity::customers.kpi.customers')" :value="number_format($stats['customers'])" icon="users" :comparison="__('identity::customers.kpi.customers_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.kpi.active')" :value="number_format($stats['active'])" icon="check-circle" :comparison="__('identity::customers.kpi.active_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.kpi.buyers')" :value="number_format($stats['buyers'])" icon="cart" :comparison="__('identity::customers.kpi.buyers_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.kpi.average')" :value="$stats['average']->format()" icon="wallet" :comparison="__('identity::customers.kpi.average_hint')" />
        </div>

        <x-ui::datatable
            id="vendor-customers"
            :url="vendor_route('sales.customers.data')"
            :columns="[
                ['data' => 'id', 'title' => __('identity::customers.columns.id')],
                ['data' => 'customer', 'title' => __('identity::customers.columns.customer')],
                ['data' => 'contact', 'title' => __('identity::customers.columns.contact')],
                ['data' => 'orders', 'title' => __('identity::customers.columns.orders'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('identity::customers.columns.status')],
                ['data' => 'lifetime', 'title' => __('identity::customers.columns.lifetime'), 'align' => 'end'],
                ['data' => 'last_order', 'title' => __('identity::customers.columns.last_order')],
                ['data' => 'actions', 'title' => __('identity::customers.columns.actions'), 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('identity::customers.empty_title')"
            :empty-description="__('identity::customers.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-customers" :search-placeholder="__('identity::customers.search')">
                    <x-ui::select name="status" :label="__('identity::customers.filters.status')" label-hidden :options="$statuses" :placeholder="__('identity::customers.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('identity::customers.filters.status')" class="w-40" />
                    <x-ui::select name="segment" :label="__('identity::customers.filters.segment')" label-hidden :options="$segments" :placeholder="__('identity::customers.filters.segment')" :search="false" allow-clear data-filter :data-filter-label="__('identity::customers.filters.segment')" class="w-44" />
                    <x-ui::daterange name="joined" from-name="from" to-name="to" :label="__('identity::customers.filters.joined')" label-hidden filter compact class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="vendor-customer-modal" :data-url="vendor_route('sales.customers.create')">{{ __('identity::customers.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @if ($canManage)
        @include('identity::vendor.customers._modal', ['id' => 'vendor-customer-modal', 'title' => __('identity::customers.create_title'), 'form' => 'vendor-customer-form'])
    @endif
</x-vendor::layout>
