{{--
    Vendor › Orders (VEN-020, Figma 12:15191): KPI cards (orders, paid, processing,
    collected), order queue (product, customer, value, your share, status, gateway, placed
    at) with search + status / payment / gateway / type / date filters and the CSV report.
--}}
@php $staff = auth('vendor')->user(); @endphp
<x-vendor::layout :title="__('commerce::vendor_orders.title')" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('commerce::vendor_orders.title')" :subtitle="__('commerce::vendor_orders.subtitle')">
            @if ($staff?->can('orders.export'))
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="download" :href="vendor_route('sales.orders.export')" data-export-link>{{ __('commerce::vendor_orders.report') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card :label="__('commerce::vendor_orders.kpi.orders')" :value="number_format($stats['orders'])" icon="box" :comparison="__('commerce::vendor_orders.kpi.orders_hint')" />
            <x-ui::kpi-card :label="__('commerce::vendor_orders.kpi.paid')" :value="number_format($stats['paid'])" icon="check-circle" :comparison="__('commerce::vendor_orders.kpi.paid_hint')" />
            <x-ui::kpi-card :label="__('commerce::vendor_orders.kpi.processing')" :value="number_format($stats['processing'])" icon="clock" :comparison="__('commerce::vendor_orders.kpi.processing_hint')" />
            <x-ui::kpi-card :label="__('commerce::vendor_orders.kpi.collected')" :value="$stats['collected']->format()" icon="wallet" :comparison="__('commerce::vendor_orders.kpi.collected_hint')" />
        </div>

        <x-ui::datatable
            id="vendor-orders"
            :url="vendor_route('sales.orders.data')"
            :columns="[
                ['data' => 'order', 'title' => __('commerce::vendor_orders.columns.order')],
                ['data' => 'customer', 'title' => __('commerce::vendor_orders.columns.customer')],
                ['data' => 'value', 'title' => __('commerce::vendor_orders.columns.value'), 'align' => 'end'],
                ['data' => 'commission', 'title' => __('commerce::vendor_orders.columns.commission'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('commerce::vendor_orders.columns.status')],
                ['data' => 'gateway', 'title' => __('commerce::vendor_orders.columns.gateway')],
                ['data' => 'placed', 'title' => __('commerce::vendor_orders.columns.placed')],
                ['data' => 'actions', 'title' => __('commerce::vendor_orders.columns.actions'), 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[6, 'desc']]"
            :empty-title="__('commerce::vendor_orders.empty_title')"
            :empty-description="__('commerce::vendor_orders.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-orders" :search-placeholder="__('commerce::vendor_orders.search')">
                    <x-ui::select name="status" :label="__('commerce::vendor_orders.filters.status')" label-hidden :options="$statuses" :placeholder="__('commerce::vendor_orders.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::vendor_orders.filters.status')" class="w-40" />
                    <x-ui::select name="payment" :label="__('commerce::vendor_orders.filters.payment')" label-hidden :options="$payments" :placeholder="__('commerce::vendor_orders.filters.payment')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::vendor_orders.filters.payment')" class="w-40" />
                    <x-ui::select name="gateway" :label="__('commerce::vendor_orders.filters.gateway')" label-hidden :options="$gateways" :placeholder="__('commerce::vendor_orders.filters.gateway')" allow-clear data-filter :data-filter-label="__('commerce::vendor_orders.filters.gateway')" class="w-40" />
                    <x-ui::select name="kind" :label="__('commerce::vendor_orders.filters.kind')" label-hidden :options="$kinds" :placeholder="__('commerce::vendor_orders.filters.kind')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::vendor_orders.filters.kind')" class="w-44" />
                    <x-ui::daterange name="placed" from-name="from" to-name="to" :label="__('commerce::vendor_orders.filters.date')" label-hidden filter compact class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-vendor::layout>
