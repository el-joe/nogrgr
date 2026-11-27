{{--
    "Your store's performance" (VEN-009, client frame 12:11267): page-level title + period
    select ("12 Months"), four KPI cards (Orders, Average order, Outstanding, Customers with
    the change vs the previous window), "Gross vs Collected" line chart and "Status Mix"
    doughnut, then the latest orders. Data: Reporting facts in USD
    (VendorDashboardPerformanceService); money values also show the display conversion
    into the selected country's currency when its rate was synced. Only for analytics.view.
    @var \App\Modules\Reporting\DTOs\VendorPerformanceData $performance
    @var list<\App\Modules\Reporting\Enums\DashboardPeriod> $periods
--}}
@php
    $baseQuery = array_filter(['country' => request()->query('country')], fn ($value) => is_string($value) && $value !== '');
    $statusTones = collect($performance->statusMix)->pluck('tone')->all();
@endphp
<section class="grid gap-6" aria-labelledby="performance-title" data-reveal="fade-up">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="grid min-w-0 gap-1">
            <h2 id="performance-title" class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ __('reporting::vendor_dashboard.title') }}</h2>
            <p class="text-sm text-ink-muted">{{ __('reporting::vendor_dashboard.subtitle') }} <span class="whitespace-nowrap" dir="ltr">({{ $performance->periodLabel }})</span></p>
        </div>
        <x-ui::dropdown align="end" width="200" :label="__('reporting::vendor_dashboard.period')">
            <x-slot:trigger>
                <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="vnd-field-box flex h-12 min-w-[150px] items-center justify-between gap-3 px-4 text-sm text-ink" data-period-trigger>
                    <span>{{ $performance->period->label() }}</span>
                    <x-ui::icon name="chevron-down" :size="20" />
                </button>
            </x-slot:trigger>
            @foreach ($periods as $period)
                <x-ui::dropdown-item :href="vendor_route('dashboard', [...$baseQuery, 'period' => $period->value]).'#performance-title'" :aria-current="$performance->period === $period ? 'true' : 'false'">{{ $period->label() }}</x-ui::dropdown-item>
            @endforeach
        </x-ui::dropdown>
    </header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($performance->kpis as $kpi)
            <x-ui::kpi-card
                :label="$kpi['label']"
                :value="$kpi['value']"
                :icon="$kpi['icon']"
                :delta="$kpi['delta']"
                :comparison="$kpi['converted'] !== null ? __('reporting::vendor_dashboard.converted', ['amount' => $kpi['converted']]) : $kpi['note']"
                :title="$kpi['note']"
                :count-up="$kpi['icon'] === 'cart' || $kpi['icon'] === 'users' ? $kpi['raw'] : null"
                data-kpi="{{ $kpi['key'] }}"
                data-reveal="fade-up"
                :data-reveal-delay="$loop->index * 60"
            />
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-ui::chart-card
            id="dashboard-gross-collected"
            :title="__('reporting::vendor_dashboard.chart.title')"
            :subtitle="__('reporting::vendor_dashboard.chart.subtitle')"
            type="line"
            :height="260"
            :ranges="[$performance->period->value => $performance->grossChart]"
            :empty="! $performance->hasOrders()"
            :empty-title="__('reporting::vendor_dashboard.empty_title')"
            :empty-description="__('reporting::vendor_dashboard.empty_description')"
        />
        <x-ui::chart-card
            id="dashboard-status-mix"
            :title="__('reporting::vendor_dashboard.status.title')"
            :subtitle="__('reporting::vendor_dashboard.status.subtitle')"
            type="doughnut"
            :height="180"
            :total="trans_choice('reporting::vendor_dashboard.status.total', $performance->placedTotal, ['count' => \Illuminate\Support\Number::format($performance->placedTotal, locale: \App\Shared\Support\Locale::numberLocale())])"
            :ranges="[$performance->period->value => ['labels' => collect($performance->statusMix)->pluck('label')->all(), 'series' => [['label' => __('reporting::vendor_dashboard.status.title'), 'data' => collect($performance->statusMix)->pluck('count')->all(), 'tones' => $statusTones]]]]"
            :empty="$performance->placedTotal === 0"
            :empty-title="__('reporting::vendor_dashboard.empty_title')"
            :empty-description="__('reporting::vendor_dashboard.empty_description')"
        />
    </div>

    <section class="grid gap-4 rounded-[12px] bg-surface p-4 sm:p-6" aria-labelledby="latest-orders-title">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="grid gap-1">
                <h3 id="latest-orders-title" class="text-2xl font-semibold leading-[1.5] text-[var(--vnd-card-title)]">{{ __('reporting::vendor_dashboard.latest.title') }}</h3>
                <p class="text-base text-[var(--vnd-card-sub)]">{{ __('reporting::vendor_dashboard.latest.subtitle') }}</p>
            </div>
            @if ($performance->latestOrders !== [] && $performance->latestOrders[0]['url'] !== null)
                <a href="{{ vendor_route('sales.orders.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">{{ __('reporting::vendor_dashboard.latest.all') }}<x-ui::icon name="chevron-right" :size="18" data-flip /></a>
            @endif
        </header>
        @if ($performance->latestOrders === [])
            <x-ui::empty-state variant="no-data" icon="cart" :title="__('reporting::vendor_dashboard.latest.empty_title')" :description="__('reporting::vendor_dashboard.latest.empty_description')" compact />
        @else
            <div class="overflow-x-auto">
                <table data-lead class="vnd-table w-full min-w-[640px] text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 text-start">{{ __('reporting::vendor_dashboard.latest.order') }}</th>
                            <th scope="col" class="px-4 text-start">{{ __('reporting::vendor_dashboard.latest.customer') }}</th>
                            <th scope="col" class="px-4 text-start">{{ __('reporting::vendor_dashboard.latest.status') }}</th>
                            <th scope="col" class="px-4 text-end">{{ __('reporting::vendor_dashboard.latest.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($performance->latestOrders as $order)
                            <tr>
                                <td class="px-4 py-3">
                                    @if ($order['url'] !== null)
                                        <a href="{{ $order['url'] }}" class="font-medium text-ink hover:text-primary" dir="ltr">{{ $order['number'] }}</a>
                                    @else
                                        <span class="font-medium text-ink" dir="ltr">{{ $order['number'] }}</span>
                                    @endif
                                    <span class="block text-xs text-[var(--vnd-text-faint)]">{{ $order['placed_at'] }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $order['customer'] }}</td>
                                <td class="px-4 py-3"><x-ui::status :status="$order['status']" /></td>
                                <td class="px-4 py-3 text-end">
                                    <bdi dir="ltr" class="text-ink">{{ $order['total'] }}</bdi>
                                    @if ($order['converted'] !== null)
                                        <span class="block text-xs text-[var(--vnd-text-faint)]">{{ __('reporting::vendor_dashboard.converted', ['amount' => $order['converted']]) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</section>
