{{--
    Owner portal › Invoices (OWN-005, Figma Affiliate & Owner 122:104137): invoices of
    all owned stores (server-side table) with view and download (print / save as PDF).
    Inputs: $stores, $statuses.
--}}
<x-layouts.panel surface="owner" :title="__('billing::owner.invoices.title')" data-page="owner-invoices">
    <x-ui::page-header :title="__('billing::owner.invoices.title')" :subtitle="__('billing::owner.invoices.subtitle')" />

    <x-ui::datatable
        id="owner-invoices"
        :url="route('owner.invoices.data')"
        :columns="[
            ['data' => 'number', 'name' => 'issued_at', 'title' => __('billing::owner.columns.invoice'), 'searchable' => false],
            ['data' => 'store', 'title' => __('billing::owner.columns.store'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type', 'title' => __('billing::owner.columns.type'), 'searchable' => false],
            ['data' => 'total_minor', 'title' => __('billing::owner.columns.amount'), 'align' => 'end', 'searchable' => false],
            ['data' => 'status', 'title' => __('billing::owner.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'desc']]"
        :empty-title="__('billing::owner.invoices.empty_title')"
        :empty-description="__('billing::owner.invoices.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="owner-invoices" :search-placeholder="__('billing::owner.invoices.search')">
                <x-ui::select name="tenant_id" :label="__('billing::owner.columns.store')" label-hidden :options="$stores" :placeholder="__('billing::owner.all_stores')" allow-clear data-filter :data-filter-label="__('billing::owner.columns.store')" class="w-44" />
                <x-ui::select name="status" :label="__('billing::owner.columns.status')" label-hidden :options="$statuses" :placeholder="__('billing::owner.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::owner.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
