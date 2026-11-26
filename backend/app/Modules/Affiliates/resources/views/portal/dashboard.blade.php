{{--
    Affiliate › Dashboard (AFF-009, Figma Affiliate & Owner 121:100334 · mobile 121:102977):
    welcome + "Request payout", KPI cards (clicks 30 days, conversions, pending commission,
    available to withdraw — USD), clicks & conversions chart, referral link card, recent
    conversions. Inputs: $affiliate, $overview, $chart, $recent, $promoCode.
--}}
@php
    $vs = __('affiliates::panel.dashboard.vs_previous');
    $locale = app()->getLocale();
    $canRequest = $overview['canRequest'] && auth('affiliate')->user()?->can('payouts.request');
@endphp
<x-layouts.panel surface="affiliate" :title="__('affiliates::panel.dashboard.title')" data-page="affiliate-dashboard">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.dashboard.welcome', ['name' => \Illuminate\Support\Str::before($affiliate->name, ' ')])" :subtitle="__('affiliates::panel.dashboard.subtitle')">
            <x-slot:actions>
                @if ($canRequest)
                    <x-ui::button icon="wallet" data-overlay-open="payout-request-modal">{{ __('affiliates::panel.payouts.request') }}</x-ui::button>
                @elseif (auth('affiliate')->user()?->can('payouts.view'))
                    <x-ui::button icon="wallet" variant="secondary" :href="route('affiliate.payouts.index')">{{ __('affiliates::panel.payouts.title') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card icon="link" :label="__('affiliates::panel.dashboard.clicks')" :value="\Illuminate\Support\Number::format($overview['clicks'], locale: \App\Shared\Support\Locale::numberLocale())" :delta="$overview['clicksDelta']" :comparison="$vs" :spark="$overview['spark']['clicks']" />
            <x-ui::kpi-card icon="check-circle" :label="__('affiliates::panel.dashboard.conversions')" :value="\Illuminate\Support\Number::format($overview['conversions'], locale: \App\Shared\Support\Locale::numberLocale())" :delta="$overview['conversionsDelta']" :comparison="$vs" :spark="$overview['spark']['conversions']" />
            <x-ui::kpi-card icon="clock" :label="__('affiliates::panel.dashboard.pending_commission')" :value="$overview['pendingCommission']->format()" :comparison="__('affiliates::panel.dashboard.pending_hint')" />
            <x-ui::kpi-card icon="wallet" highlight :label="__('affiliates::panel.dashboard.available')" :value="$overview['requestable']->format()" :comparison="__('affiliates::panel.dashboard.balance', ['amount' => $overview['balance']->format()])" />
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <x-ui::chart-card id="affiliate-clicks-chart" :title="__('affiliates::panel.dashboard.chart_title')" :subtitle="__('affiliates::panel.dashboard.chart_subtitle')" :ranges="$chart" range="30d"
                :empty="$overview['clicksTotal'] === 0 && $overview['conversionsTotal'] === 0"
                :empty-title="__('affiliates::panel.dashboard.chart_empty_title')" :empty-description="__('affiliates::panel.dashboard.chart_empty_text')" />

            @include('affiliates::portal._referral-card', ['promoCode' => $promoCode, 'linksUrl' => route('affiliate.links')])
        </div>

        <x-ui::card :title="__('affiliates::panel.dashboard.recent')" :padding="false" id="recent-conversions">
            <x-slot:actions>
                <a href="{{ route('affiliate.conversions') }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('affiliates::panel.dashboard.view_all') }}</a>
            </x-slot:actions>
            @if ($recent->isEmpty())
                <x-ui::empty-state icon="chart" :title="__('affiliates::panel.conversions.empty_title')" :description="__('affiliates::panel.conversions.empty_text')" compact class="pb-6" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-subtle text-xs text-ink-secondary">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('affiliates::panel.conversions.store') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('affiliates::panel.conversions.sale') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('affiliates::panel.conversions.commission') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('affiliates::panel.conversions.status') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('affiliates::panel.conversions.date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recent as $conversion)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="block font-medium text-ink">{{ $conversion->tenant->name ?? __('affiliates::panel.conversions.store_removed') }}</span>
                                        <span class="block text-xs text-ink-secondary" dir="ltr">{{ $conversion->reference() }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-end text-ink" dir="ltr">{{ $conversion->sale_amount > 0 ? \App\Shared\Money\Money::ofMinor($conversion->sale_amount)->format() : '—' }}</td>
                                    <td class="px-5 py-3 text-end font-semibold text-ink" dir="ltr">{{ $conversion->commission()->format() }}</td>
                                    <td class="px-5 py-3"><x-ui::badge :tone="$conversion->status->tone()" dot>{{ $conversion->status->label() }}</x-ui::badge></td>
                                    <td class="px-5 py-3 text-ink">{{ $conversion->created_at?->locale($locale)->translatedFormat('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui::card>
    </div>

    @if ($canRequest)
        @include('affiliates::portal._request-modal', ['action' => route('affiliate.payouts.requests.store'), 'title' => __('affiliates::panel.payouts.request')])
    @endif
</x-layouts.panel>
