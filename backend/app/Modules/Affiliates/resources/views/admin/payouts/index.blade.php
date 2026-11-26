{{--
    Admin › Affiliates › Payouts (ADM-062, Figma Admin Panel 74:67899): activity tabs,
    payout requests by status (pending / paid / rejected) + payout history, row details
    = "Pay out" modal (Reject / Mark paid).
--}}
@php
    $statusItems = ['all' => ['label' => __('affiliates::admin.payouts.tabs.all'), 'href' => route('admin.affiliates.payouts.index')]];
    foreach (\App\Modules\Affiliates\Enums\PayoutRequestStatus::cases() as $status) {
        $statusItems[$status->value] = ['label' => $status->label(), 'href' => route('admin.affiliates.payouts.index', ['status' => $status->value]), 'count' => number_format($counts[$status->value])];
    }
    $statusItems['history'] = ['label' => __('affiliates::admin.payouts.tabs.history'), 'href' => route('admin.affiliates.payouts.history')];
@endphp
<x-layouts.panel surface="admin" :title="__('affiliates::admin.payouts.title')" data-page="admin-affiliates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::admin.activity.title')" :subtitle="__('affiliates::admin.payouts.subtitle')"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('navigation.admin.affiliates') => null, __('navigation.admin.affiliate_payouts') => null]">
            <x-slot:tabs>
                @include('affiliates::admin.partials.activity-tabs', ['active' => 'payouts', 'pending' => $counts['pending']])
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::tabs id="payout-status" variant="segmented" :active="$active" :label="__('affiliates::admin.payouts.title')" :items="$statusItems" />

        <x-ui::datatable
            id="payout-requests"
            :url="route('admin.affiliates.payouts.data')"
            :columns="[
                ['data' => 'request', 'title' => __('affiliates::admin.payouts.columns.request')],
                ['data' => 'affiliate', 'title' => __('affiliates::admin.payouts.columns.affiliate'), 'orderable' => false],
                ['data' => 'amount', 'title' => __('affiliates::admin.payouts.columns.amount'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('affiliates::admin.payouts.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('affiliates::admin.payouts.empty_title')"
            :empty-description="__('affiliates::admin.payouts.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="payout-requests" :search-placeholder="__('affiliates::admin.payouts.search')">
                    @if ($active !== 'all')
                        <input type="hidden" name="status" value="{{ $active }}" data-filter>
                    @endif
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="affiliate-modal" :title="__('affiliates::admin.payouts.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
