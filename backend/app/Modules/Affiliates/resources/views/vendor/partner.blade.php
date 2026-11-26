{{--
    Vendor › Partner program (VEN-055, Figma Vendor Panel (new screens) 87:111264): the
    store's own affiliate account (created automatically) — balance (withdrawable), clicks,
    conversions, earned (USD), referral link + promo code, conversions and payouts lists,
    "Open affiliate dashboard" (one-time SSO, new tab) and "Withdraw" (VEN-055a modal).
    Inputs: $affiliate, $overview, $promoCode, $canManage.
--}}
@php
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $canWithdraw = $canManage && $overview['canRequest'];
@endphp
<x-vendor::layout :title="__('affiliates::panel.partner.title')" page="vendor-partner">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.partner.title')" :subtitle="__('affiliates::panel.partner.subtitle', ['commission' => $overview['commission']])" :breadcrumbs="[__('affiliates::panel.partner.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <form method="POST" action="{{ vendor_route('partner.sso') }}" target="_blank">
                        @csrf
                        <x-ui::button type="submit" variant="secondary" icon="link">{{ __('affiliates::panel.partner.open_dashboard') }}</x-ui::button>
                    </form>
                    <x-ui::button icon="wallet" data-overlay-open="payout-request-modal" :disabled="! $canWithdraw">{{ __('affiliates::panel.partner.withdraw') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if (! $affiliate->isActive())
            <p class="flex items-start gap-2 rounded-control bg-warning-bg px-4 py-3 text-sm text-ink">
                <x-ui::icon name="info" :size="18" class="shrink-0 text-warning" />{{ __('affiliates::panel.partner.suspended') }}
            </p>
        @elseif ($canManage && ! $canWithdraw)
            <p class="flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-sm text-ink" data-payout-blocked>
                <x-ui::icon name="info" :size="18" class="shrink-0 text-info" />{{ __('affiliates::panel.payouts.below_minimum_notice', ['amount' => $overview['minimum']->format()]) }}
            </p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card icon="wallet" highlight :label="__('affiliates::panel.partner.balance')" :value="$overview['requestable']->format()" :comparison="__('affiliates::panel.partner.withdrawable')" />
            <x-ui::kpi-card icon="zap" :label="__('affiliates::panel.partner.clicks')" :value="$number($overview['clicks'])" :delta="$overview['clicksDelta']" :comparison="__('affiliates::panel.dashboard.vs_previous')" :spark="$overview['spark']['clicks']" />
            <x-ui::kpi-card icon="users" :label="__('affiliates::panel.dashboard.conversions')" :value="$number($overview['conversions'])" :delta="$overview['conversionsDelta']" :comparison="__('affiliates::panel.dashboard.vs_previous')" :spark="$overview['spark']['conversions']" />
            <x-ui::kpi-card icon="gift" :label="__('affiliates::panel.partner.earned')" :value="$overview['earned']->format()" :comparison="__('affiliates::panel.dashboard.pending_amount', ['amount' => $overview['pendingCommission']->format()])" />
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            @include('affiliates::portal._referral-card', ['promoCode' => $promoCode])

            <x-ui::datatable
                id="partner-conversions"
                :url="vendor_route('partner.conversions.data')"
                :columns="[
                    ['data' => 'store', 'title' => __('affiliates::panel.conversions.store'), 'orderable' => false],
                    ['data' => 'commission', 'title' => __('affiliates::panel.conversions.commission'), 'align' => 'end'],
                    ['data' => 'status', 'title' => __('affiliates::panel.conversions.status')],
                ]"
                :order="[[1, 'desc']]"
                :page-length="5"
                :empty-title="__('affiliates::panel.conversions.empty_title')"
                :empty-description="__('affiliates::panel.partner.conversions_empty')"
            />
        </div>

        <section class="grid gap-3" aria-labelledby="partner-payouts-title">
            <h2 id="partner-payouts-title" class="text-base font-semibold text-ink">{{ __('affiliates::panel.payouts.tabs.history') }}</h2>
            <x-ui::datatable
                id="partner-payouts"
                :url="vendor_route('partner.payouts.data')"
                :columns="[
                    ['data' => 'payout', 'title' => __('affiliates::panel.payouts.columns.payout')],
                    ['data' => 'method', 'title' => __('affiliates::panel.payouts.columns.method'), 'orderable' => false],
                    ['data' => 'amount', 'title' => __('affiliates::panel.payouts.columns.amount'), 'align' => 'end'],
                ]"
                :order="[[0, 'desc']]"
                :empty-title="__('affiliates::panel.payouts.history_empty_title')"
                :empty-description="__('affiliates::panel.partner.payouts_empty')"
            />
        </section>
    </div>

    @if ($canWithdraw)
        @include('affiliates::portal._request-modal', ['action' => vendor_route('partner.withdraw'), 'title' => __('affiliates::panel.partner.withdraw_title')])
    @endif
</x-vendor::layout>
