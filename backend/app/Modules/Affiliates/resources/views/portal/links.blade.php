{{--
    Affiliate › Links & promo codes (AFF-010, Figma Affiliate & Owner 121:100742): referral
    link (copy) with all-time clicks, conversions and conversion rate; link builder for any
    central page; promo codes issued by the admin (server-side list). Inputs: $affiliate, $overview.
--}}
@php
    $number = fn (int|float $value) => \Illuminate\Support\Number::format($value, maxPrecision: 1, locale: \App\Shared\Support\Locale::numberLocale());
    $rate = $overview['clicksTotal'] > 0 ? $overview['conversionsTotal'] / $overview['clicksTotal'] * 100 : 0;
@endphp
<x-layouts.panel surface="affiliate" :title="__('affiliates::panel.links.title')" data-page="affiliate-links">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.links.title')" :subtitle="__('affiliates::panel.links.subtitle')" />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui::kpi-card icon="link" :label="__('affiliates::panel.links.clicks_total')" :value="$number($overview['clicksTotal'])" />
            <x-ui::kpi-card icon="check-circle" :label="__('affiliates::panel.links.conversions_total')" :value="$number($overview['conversionsTotal'])" />
            <x-ui::kpi-card icon="percent" :label="__('affiliates::panel.links.rate')" :value="$number($rate).'%'" />
        </div>

        <x-ui::card :title="__('affiliates::panel.links.link_title')" :subtitle="trans_choice('affiliates::panel.referral.subtitle', $overview['cookieDays'], ['days' => $overview['cookieDays']])">
            <div class="flex flex-wrap items-center gap-3 rounded-control border border-line bg-subtle px-4 py-3">
                <x-ui::icon name="link" :size="18" class="shrink-0 text-ink-brand" />
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink" dir="ltr">{{ $overview['link'] }}</span>
                <x-ui::button size="sm" variant="secondary" icon="copy" :data-copy-text="$overview['link']">{{ __('affiliates::panel.referral.copy') }}</x-ui::button>
            </div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div class="grid gap-0.5">
                    <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.links.code') }}</dt>
                    <dd class="font-semibold text-ink" dir="ltr">{{ $affiliate->code }}</dd>
                </div>
                <div class="grid gap-0.5">
                    <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.referral.commission') }}</dt>
                    <dd class="font-semibold text-ink" dir="ltr">{{ $overview['commission'] }}</dd>
                </div>
                <div class="grid gap-0.5">
                    <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.links.cookie') }}</dt>
                    <dd class="font-semibold text-ink">{{ trans_choice('affiliates::panel.links.days', $overview['cookieDays'], ['count' => $overview['cookieDays']]) }}</dd>
                </div>
            </dl>
            <p class="mt-4 flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-xs text-ink">
                <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />{{ __('affiliates::panel.links.how') }}
            </p>
        </x-ui::card>

        <x-ui::datatable
            id="promo-codes"
            :url="route('affiliate.links.promo-codes')"
            :columns="[
                ['data' => 'code', 'title' => __('affiliates::panel.links.columns.code')],
                ['data' => 'discount', 'title' => __('affiliates::panel.links.columns.discount'), 'orderable' => false],
                ['data' => 'uses', 'title' => __('affiliates::panel.links.columns.uses')],
                ['data' => 'validity', 'title' => __('affiliates::panel.links.columns.validity'), 'orderable' => false],
                ['data' => 'state', 'title' => __('affiliates::panel.links.columns.state'), 'orderable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('affiliates::panel.links.empty_title')"
            :empty-description="__('affiliates::panel.links.empty_text')"
        />
    </div>
</x-layouts.panel>
