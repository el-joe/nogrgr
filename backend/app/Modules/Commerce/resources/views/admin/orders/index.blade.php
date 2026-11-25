{{--
    Admin › Sales › Orders (ADM-020, Figma Admin Panel 48:16236): every store's orders from
    order_facts — status tabs with counts, search + status / payment / gateway / store /
    country / date filters, CSV export, link to the shipping queue. Own-products orders
    are listed but read-only.
--}}
@php
    $tabItems = [];
    foreach ($tabs as $key => $tab) {
        $tabItems[$key] = ['label' => $tab['label'], 'count' => number_format($tab['count']), 'href' => route('admin.orders.index', $key === 'all' ? [] : ['status' => $key])];
    }
    $admin = auth('admin')->user();
@endphp
<x-layouts.panel surface="admin" :title="__('commerce::orders_admin.title')" data-page="admin-orders">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('commerce::orders_admin.title')"
            :subtitle="__('commerce::orders_admin.subtitle')"
            :breadcrumbs="[__('navigation.sections.sales') => null, __('commerce::orders_admin.title') => null]"
        >
            <x-slot:actions>
                @if ($admin?->can('orders.export'))
                    <x-ui::button variant="secondary" icon="download" :href="route('admin.orders.export', request()->only('status'))" data-export-link>{{ __('commerce::orders_admin.export') }}</x-ui::button>
                @endif
                @if ($admin?->can('shipping-queue.view'))
                    <x-ui::button icon="truck" :href="route('admin.shipping-queue.index')">{{ __('navigation.admin.shipping_queue') }}</x-ui::button>
                @endif
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="orders-status-tabs" :active="$active" :label="__('commerce::orders_admin.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="admin-orders"
            :url="route('admin.orders.data')"
            :columns="[
                ['data' => 'order', 'title' => __('commerce::orders_admin.columns.order')],
                ['data' => 'vendor', 'title' => __('commerce::orders_admin.columns.vendor')],
                ['data' => 'customer', 'title' => __('commerce::orders_admin.columns.customer')],
                ['data' => 'total', 'title' => __('commerce::orders_admin.columns.total'), 'align' => 'end'],
                ['data' => 'payment', 'title' => __('commerce::orders_admin.columns.payment')],
                ['data' => 'status', 'title' => __('commerce::orders_admin.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('commerce::orders_admin.empty_title')"
            :empty-description="__('commerce::orders_admin.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="admin-orders" :search-placeholder="__('commerce::orders_admin.search')">
                    @if ($active !== 'all')
                        <input type="hidden" name="status" value="{{ $active }}" data-filter>
                    @endif
                    <x-ui::select name="payment" :label="__('commerce::orders_admin.filters.payment')" label-hidden :options="$payments" :placeholder="__('commerce::orders_admin.filters.payment')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::orders_admin.filters.payment')" class="w-40" />
                    <x-ui::select name="gateway" :label="__('commerce::orders_admin.filters.gateway')" label-hidden :options="$gateways" :placeholder="__('commerce::orders_admin.filters.gateway')" allow-clear data-filter :data-filter-label="__('commerce::orders_admin.filters.gateway')" class="w-40" />
                    <x-ui::select name="tenant_id" :label="__('commerce::orders_admin.filters.vendor')" label-hidden :options="[]" :placeholder="__('commerce::orders_admin.filters.vendor')" :ajax-url="route('admin.orders.stores')" allow-clear data-filter :data-filter-label="__('commerce::orders_admin.filters.vendor')" class="w-48" />
                    <x-ui::select name="country_id" :label="__('commerce::orders_admin.filters.country')" label-hidden :options="$countries" :placeholder="__('commerce::orders_admin.filters.country')" allow-clear data-filter :data-filter-label="__('commerce::orders_admin.filters.country')" class="w-44" />
                    <x-ui::daterange name="placed" from-name="from" to-name="to" :label="__('commerce::orders_admin.filters.date')" label-hidden filter compact class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
