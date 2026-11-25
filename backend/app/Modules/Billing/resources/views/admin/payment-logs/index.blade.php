{{--
    Admin › Finance › Payment logs (ADM-055, Figma 74:66052): server-side table filtered
    by gateway / status with search on reference, gateway ref or store.
--}}
<x-layouts.panel surface="admin" :title="__('billing::payments.title')">
    <x-ui::page-header
        :title="__('billing::payments.title')"
        :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::payments.title') => null]"
    />

    <x-ui::datatable
        id="payment-logs"
        :url="route('admin.finance.payment-logs.data')"
        :columns="[
            ['data' => 'id', 'title' => __('billing::payments.columns.log'), 'searchable' => false],
            ['data' => 'gateway', 'title' => __('billing::payments.columns.gateway'), 'searchable' => false],
            ['data' => 'purpose', 'title' => __('billing::payments.columns.purpose'), 'searchable' => false],
            ['data' => 'amount_minor', 'title' => __('billing::payments.columns.amount'), 'align' => 'end', 'searchable' => false],
            ['data' => 'status', 'title' => __('billing::payments.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'desc']]"
        :empty-title="__('billing::payments.empty_title')"
        :empty-description="__('billing::payments.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="payment-logs" :search-placeholder="__('billing::payments.search')">
                <x-ui::select name="gateway" :label="__('billing::payments.columns.gateway')" label-hidden :options="$gateways" :placeholder="__('billing::payments.columns.gateway')" :search="false" allow-clear data-filter :data-filter-label="__('billing::payments.columns.gateway')" class="w-40" />
                <x-ui::select name="status" :label="__('billing::payments.columns.status')" label-hidden :options="$statuses" :placeholder="__('billing::payments.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::payments.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
