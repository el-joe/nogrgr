{{--
    Affiliate › Payouts (AFF-012, Figma Affiliate & Owner 121:101567): balance summary,
    "Request payout" (AFF-012a modal; disabled with the reason when nothing is requestable
    or payout details are missing), payout requests and paid payouts (tabs, server-side).
    Inputs: $affiliate, $overview, $statuses.
--}}
@php
    $user = auth('affiliate')->user();
    $mayRequest = (bool) $user?->can('payouts.request');
    $missingDetails = $affiliate->payout_method === null;
    $canRequest = $mayRequest && $overview['canRequest'] && ! $missingDetails;
@endphp
<x-layouts.panel surface="affiliate" :title="__('affiliates::panel.payouts.title')" data-page="affiliate-payouts">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.payouts.title')" :subtitle="__('affiliates::panel.payouts.subtitle')">
            @if ($mayRequest)
                <x-slot:actions>
                    <x-ui::button icon="wallet" data-overlay-open="payout-request-modal" :disabled="! $canRequest">{{ __('affiliates::panel.payouts.request') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($mayRequest && ! $canRequest)
            <p class="flex items-start gap-2 rounded-control bg-warning-bg px-4 py-3 text-sm text-ink" data-payout-blocked>
                <x-ui::icon name="info" :size="18" class="shrink-0 text-warning" />
                <span>
                    @if (! $affiliate->isActive())
                        {{ __('affiliates::panel.payouts.errors.inactive') }}
                    @elseif ($missingDetails)
                        {{ __('affiliates::panel.payouts.errors.no_details') }}
                        @if ($user?->can('profile.manage'))
                            <a href="{{ route('affiliate.profile.edit', ['tab' => 'payout']) }}" class="font-medium text-ink-brand hover:underline">{{ __('affiliates::panel.payouts.add_details') }}</a>
                        @endif
                    @else
                        {{ __('affiliates::panel.payouts.below_minimum_notice', ['amount' => $overview['minimum']->format()]) }}
                    @endif
                </span>
            </p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card icon="wallet" highlight :label="__('affiliates::panel.dashboard.available')" :value="$overview['requestable']->format()" />
            <x-ui::kpi-card icon="clock" :label="__('affiliates::panel.referral.pending_requests')" :value="$overview['pendingRequests']->format()" />
            <x-ui::kpi-card icon="download" :label="__('affiliates::panel.payouts.total_paid')" :value="$overview['paid']->format()" />
            <x-ui::kpi-card icon="award" :label="__('affiliates::panel.conversions.earned')" :value="$overview['earned']->format()" />
        </div>

        <x-ui::tabs id="payout-tabs" :label="__('affiliates::panel.payouts.title')" :items="[
            'requests' => __('affiliates::panel.payouts.tabs.requests'),
            'history' => __('affiliates::panel.payouts.tabs.history'),
        ]" />

        <x-ui::tab-panel tabs="payout-tabs" key="requests" :active="request()->query('tab', 'requests') !== 'history'">
            <x-ui::datatable
                id="payout-requests"
                :url="route('affiliate.payouts.requests.data')"
                :columns="[
                    ['data' => 'request', 'title' => __('affiliates::panel.payouts.columns.request')],
                    ['data' => 'amount', 'title' => __('affiliates::panel.payouts.columns.amount'), 'align' => 'end'],
                    ['data' => 'status', 'title' => __('affiliates::panel.payouts.columns.status')],
                    ['data' => 'note', 'title' => __('affiliates::panel.payouts.columns.note'), 'orderable' => false],
                ]"
                :order="[[0, 'desc']]"
                :empty-title="__('affiliates::panel.payouts.requests_empty_title')"
                :empty-description="__('affiliates::panel.payouts.requests_empty_text')"
            >
                <x-slot:filters>
                    <x-ui::filters-bar target="payout-requests" :search="false">
                        <x-ui::select name="status" :label="__('affiliates::panel.payouts.columns.status')" label-hidden :options="$statuses" :placeholder="__('affiliates::panel.conversions.all_statuses')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::panel.payouts.columns.status')" class="w-44" />
                    </x-ui::filters-bar>
                </x-slot:filters>
            </x-ui::datatable>
        </x-ui::tab-panel>

        <x-ui::tab-panel tabs="payout-tabs" key="history" :active="request()->query('tab') === 'history'">
            <x-ui::datatable
                id="payout-history"
                :url="route('affiliate.payouts.history.data')"
                :columns="[
                    ['data' => 'payout', 'title' => __('affiliates::panel.payouts.columns.payout')],
                    ['data' => 'method', 'title' => __('affiliates::panel.payouts.columns.method'), 'orderable' => false],
                    ['data' => 'amount', 'title' => __('affiliates::panel.payouts.columns.amount'), 'align' => 'end'],
                ]"
                :order="[[0, 'desc']]"
                :empty-title="__('affiliates::panel.payouts.history_empty_title')"
                :empty-description="__('affiliates::panel.payouts.history_empty_text')"
            />
        </x-ui::tab-panel>
    </div>

    @if ($canRequest)
        @include('affiliates::portal._request-modal', ['action' => route('affiliate.payouts.requests.store'), 'title' => __('affiliates::panel.payouts.request')])
    @endif
</x-layouts.panel>
