{{--
    Admin › Finance › Ledger (ADM-050, Figma Admin Panel 74:59026): KPI cards (owed to
    vendors, owed to platform, settled in 30 days, open payouts), balances per store with
    search / balance / country filters, CSV export, view / mark settlement paid / create
    payout per row.
--}}
@php
    $money = fn (int $minor) => \App\Shared\Money\Money::ofMinor($minor)->format();
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.ledger.title')" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.ledger.title')" :subtitle="__('billing::finance.ledger.subtitle')"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.ledger.title') => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.finance.ledger.export')" data-export-link>{{ __('billing::finance.export') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card :label="__('billing::finance.ledger.kpi.owed_to_vendors')" :value="$money($stats['owed_to_vendors'])" icon="wallet" :comparison="__('billing::finance.ledger.kpi.owed_to_vendors_hint')" />
            <x-ui::kpi-card :label="__('billing::finance.ledger.kpi.owed_to_platform')" :value="$money($stats['owed_to_platform'])" icon="alert-triangle" :comparison="__('billing::finance.ledger.kpi.owed_to_platform_hint')" />
            <x-ui::kpi-card :label="__('billing::finance.ledger.kpi.settled')" :value="$money($stats['settled_recent'])" icon="check-circle" :comparison="__('billing::finance.ledger.kpi.settled_hint')" />
            <x-ui::kpi-card :label="__('billing::finance.ledger.kpi.payouts_pending')" :value="$money($stats['payouts_open'])" icon="clock" :comparison="trans_choice('billing::finance.ledger.kpi.payouts_count', $stats['payouts_open_count'], ['count' => $stats['payouts_open_count']])" />
        </div>

        <x-ui::datatable
            id="ledger"
            :url="route('admin.finance.ledger.data')"
            :columns="[
                ['data' => 'tenant', 'title' => __('billing::finance.ledger.columns.tenant')],
                ['data' => 'payable', 'title' => __('billing::finance.ledger.columns.payable'), 'align' => 'end'],
                ['data' => 'receivable', 'title' => __('billing::finance.ledger.columns.receivable'), 'align' => 'end'],
                ['data' => 'last_settlement', 'title' => __('billing::finance.ledger.columns.last_settlement')],
                ['data' => 'status', 'title' => __('billing::finance.ledger.columns.status'), 'orderable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[2, 'desc']]"
            :empty-title="__('billing::finance.ledger.empty_title')"
            :empty-description="__('billing::finance.ledger.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="ledger" :search-placeholder="__('billing::finance.ledger.search')">
                    <x-ui::select name="balance" :label="__('billing::finance.ledger.filters.balance')" label-hidden :options="__('billing::finance.ledger.balance_options')" :placeholder="__('billing::finance.ledger.filters.balance')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.ledger.filters.balance')" class="w-48" />
                    <x-ui::select name="country_id" :label="__('billing::finance.ledger.filters.country')" label-hidden :options="$countries" :placeholder="__('billing::finance.ledger.filters.country')" allow-clear data-filter :data-filter-label="__('billing::finance.ledger.filters.country')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="settle-modal" :title="__('billing::finance.settle.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
