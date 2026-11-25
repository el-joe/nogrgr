{{--
    Admin › Finance › Transactions (ADM-053, Figma Admin Panel 74:63449): every ledger
    transaction (reference + type, store, direction, source, amount, date), direction /
    source / store / date filters, CSV export and the detail modal (Figma 74:68877).
--}}
<x-layouts.panel surface="admin" :title="__('billing::finance.transactions.title')" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.transactions.title')" :subtitle="__('billing::finance.transactions.subtitle')"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.transactions.title') => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.finance.transactions.export')" data-export-link>{{ __('billing::finance.export') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::datatable
            id="transactions"
            :url="route('admin.finance.transactions.data')"
            :columns="[
                ['data' => 'transaction', 'title' => __('billing::finance.transactions.columns.transaction')],
                ['data' => 'store', 'title' => __('billing::finance.transactions.columns.store'), 'orderable' => false],
                ['data' => 'direction', 'title' => __('billing::finance.transactions.columns.direction'), 'orderable' => false],
                ['data' => 'source', 'title' => __('billing::finance.transactions.columns.source'), 'orderable' => false],
                ['data' => 'amount', 'title' => __('billing::finance.transactions.columns.amount'), 'align' => 'end'],
                ['data' => 'date', 'title' => __('billing::finance.transactions.columns.date')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[5, 'desc']]"
            :empty-title="__('billing::finance.transactions.empty_title')"
            :empty-description="__('billing::finance.transactions.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="transactions" :search-placeholder="__('billing::finance.transactions.search')">
                    <x-ui::select name="direction" :label="__('billing::finance.transactions.filters.direction')" label-hidden :options="$directions" :placeholder="__('billing::finance.transactions.filters.direction')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.transactions.filters.direction')" class="w-40" />
                    <x-ui::select name="source" :label="__('billing::finance.transactions.filters.source')" label-hidden :options="$sources" :placeholder="__('billing::finance.transactions.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.transactions.filters.source')" class="w-40" />
                    <x-ui::select name="tenant_id" :label="__('billing::finance.filters.store')" label-hidden :options="[]" :placeholder="__('billing::finance.filters.store')" :ajax-url="route('admin.finance.transactions.stores')" allow-clear data-filter :data-filter-label="__('billing::finance.filters.store')" class="w-48" />
                    <x-ui::daterange name="occurred" from-name="from" to-name="to" :label="__('billing::finance.transactions.filters.date')" label-hidden filter compact class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="transaction-modal" :title="__('billing::finance.transactions.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
