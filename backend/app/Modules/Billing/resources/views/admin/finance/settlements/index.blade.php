{{--
    Admin › Finance › Settlements vendor → platform (ADM-051, Figma Admin Panel 74:60809):
    tabs all / pending / paid / failed, search, gateway / store filters, one row per
    payment (orders covered, amount, gateway or manual method, status), detail modal.
--}}
@php
    $tabItems = ['all' => ['label' => __('billing::finance.settlements.tabs.all'), 'href' => route('admin.finance.settlements.index')]];
    foreach (['pending', 'paid', 'failed'] as $status) {
        $tabItems[$status] = ['label' => \App\Modules\Commerce\Enums\SettlementStatus::from($status)->label(), 'href' => route('admin.finance.settlements.index', ['status' => $status])] + ($status !== 'paid' ? ['count' => number_format($stats[$status])] : []);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.settlements.title')" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.settlements.title')" :subtitle="__('billing::finance.settlements.subtitle')"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.settlements.title') => null]">
            <x-slot:tabs>
                <x-ui::tabs id="settlement-tabs" :active="$active" :label="__('billing::finance.settlements.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="settlements"
            :url="route('admin.finance.settlements.data')"
            :columns="[
                ['data' => 'settlement', 'title' => __('billing::finance.settlements.columns.settlement')],
                ['data' => 'store', 'title' => __('billing::finance.settlements.columns.store'), 'orderable' => false],
                ['data' => 'orders', 'title' => __('billing::finance.settlements.columns.orders'), 'align' => 'end'],
                ['data' => 'amount', 'title' => __('billing::finance.settlements.columns.amount'), 'align' => 'end'],
                ['data' => 'gateway', 'title' => __('billing::finance.settlements.columns.gateway'), 'orderable' => false],
                ['data' => 'status', 'title' => __('billing::finance.settlements.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('billing::finance.settlements.empty_title')"
            :empty-description="__('billing::finance.settlements.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="settlements" :search-placeholder="__('billing::finance.settlements.search')">
                    @if ($active !== 'all')
                        <input type="hidden" name="status" value="{{ $active }}" data-filter>
                    @endif
                    <x-ui::select name="gateway" :label="__('billing::finance.settlements.filters.gateway')" label-hidden :options="$gateways" :placeholder="__('billing::finance.settlements.filters.gateway')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.settlements.filters.gateway')" class="w-44" />
                    <x-ui::select name="tenant_id" :label="__('billing::finance.filters.store')" label-hidden :options="[]" :placeholder="__('billing::finance.filters.store')" :ajax-url="route('admin.finance.settlements.stores')" allow-clear data-filter :data-filter-label="__('billing::finance.filters.store')" class="w-48" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="settlement-modal" :title="__('billing::finance.settlements.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
