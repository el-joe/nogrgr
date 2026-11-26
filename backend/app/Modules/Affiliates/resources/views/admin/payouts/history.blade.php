{{-- Admin › Affiliates › Payouts › History (ADM-062): paid payouts — date, affiliate, method, reference, amount, proof. --}}
@php
    $statusItems = ['all' => ['label' => __('affiliates::admin.payouts.tabs.all'), 'href' => route('admin.affiliates.payouts.index')]];
    foreach (\App\Modules\Affiliates\Enums\PayoutRequestStatus::cases() as $status) {
        $statusItems[$status->value] = ['label' => $status->label(), 'href' => route('admin.affiliates.payouts.index', ['status' => $status->value]), 'count' => number_format($counts[$status->value])];
    }
    $statusItems['history'] = ['label' => __('affiliates::admin.payouts.tabs.history'), 'href' => route('admin.affiliates.payouts.history')];
@endphp
<x-layouts.panel surface="admin" :title="__('affiliates::admin.payouts.history_title')" data-page="admin-affiliates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::admin.activity.title')" :subtitle="__('affiliates::admin.payouts.history_subtitle')"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('navigation.admin.affiliates') => null, __('navigation.admin.affiliate_payouts') => route('admin.affiliates.payouts.index'), __('affiliates::admin.payouts.tabs.history') => null]">
            <x-slot:tabs>
                @include('affiliates::admin.partials.activity-tabs', ['active' => 'payouts', 'pending' => $counts['pending']])
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::tabs id="payout-status" variant="segmented" active="history" :label="__('affiliates::admin.payouts.title')" :items="$statusItems" />

        <x-ui::datatable
            id="affiliate-payouts"
            :url="route('admin.affiliates.payouts.data', ['history' => 1])"
            :columns="[
                ['data' => 'payout', 'title' => __('affiliates::admin.payouts.columns.payout')],
                ['data' => 'affiliate', 'title' => __('affiliates::admin.payouts.columns.affiliate'), 'orderable' => false],
                ['data' => 'method', 'title' => __('affiliates::admin.payout.method'), 'orderable' => false],
                ['data' => 'reference', 'title' => __('affiliates::admin.payout.reference'), 'orderable' => false],
                ['data' => 'amount', 'title' => __('affiliates::admin.payouts.columns.amount'), 'align' => 'end'],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('affiliates::admin.payouts.history_empty_title')"
            :empty-description="__('affiliates::admin.payouts.history_empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="affiliate-payouts" :search-placeholder="__('affiliates::admin.payouts.search')">
                    <x-ui::select name="method" :label="__('affiliates::admin.payout.method')" label-hidden :options="$methods" :placeholder="__('affiliates::admin.payout.method')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::admin.payout.method')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
