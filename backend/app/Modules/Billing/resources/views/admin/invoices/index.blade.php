{{--
    Admin › Finance › Invoices (ADM-054, Figma 74:64375): status tabs, server-side table
    filtered by status / type with search on number or store.
--}}
@php
    $status = request()->query('status');
    $status = is_string($status) && array_key_exists($status, $statuses) ? $status : 'all';
    $tabs = ['all' => ['label' => __('billing::invoices.tabs.all'), 'href' => route('admin.finance.invoices.index')]];
    foreach ($statuses as $key => $label) {
        $tabs[$key] = ['label' => $label, 'href' => route('admin.finance.invoices.index', ['status' => $key])] + ($key === 'unpaid' ? ['count' => $counts['unpaid'] ?? 0] : []);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('billing::invoices.title')">
    <x-ui::page-header
        :title="__('billing::invoices.title')"
        :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::invoices.title') => null]"
    >
        <x-slot:tabs>
            <x-ui::tabs id="invoice-tabs" :active="$status" :label="__('billing::invoices.title')" :items="$tabs" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="invoices"
        :url="route('admin.finance.invoices.data')"
        :columns="[
            ['data' => 'number', 'title' => __('billing::invoices.columns.invoice'), 'searchable' => false],
            ['data' => 'store', 'title' => __('billing::invoices.columns.store'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type', 'title' => __('billing::invoices.columns.type'), 'searchable' => false],
            ['data' => 'total_minor', 'title' => __('billing::invoices.columns.amount'), 'align' => 'end', 'searchable' => false],
            ['data' => 'issued_at', 'title' => __('billing::invoices.columns.issued'), 'searchable' => false],
            ['data' => 'status', 'title' => __('billing::invoices.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[4, 'desc']]"
        :empty-title="__('billing::invoices.empty_title')"
        :empty-description="__('billing::invoices.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="invoices" :search-placeholder="__('billing::invoices.search')">
                <x-ui::select name="status" :label="__('billing::invoices.columns.status')" label-hidden :options="$statuses" :placeholder="__('billing::invoices.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::invoices.columns.status')" class="w-40" />
                <x-ui::select name="type" :label="__('billing::invoices.columns.type')" label-hidden :options="$types" :placeholder="__('billing::invoices.columns.type')" :search="false" allow-clear data-filter :data-filter-label="__('billing::invoices.columns.type')" class="w-48" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
