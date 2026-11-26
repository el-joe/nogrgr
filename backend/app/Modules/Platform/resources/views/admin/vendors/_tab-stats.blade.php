{{--
    Vendor profile › Stats & orders (ADM-041e, Figma 72:51480): the store's last 12 months
    from central order facts (orders, customers, AOV, gross), orders by month and the
    store's orders table (search, status and date filters).
--}}
@php
    /** @var array{tiles: list<array{label: string, value: string}>, chart: array{labels: list<string>, data: list<int>}, statuses: array<string, string>, period: string} $stats */
    $canReports = (bool) auth('admin')->user()?->can('reports.view');
@endphp
<div class="grid gap-6 *:min-w-0">
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('reporting::vendor_stats.kpis') }}">
        @foreach ($stats['tiles'] as $tile)
            <div class="grid gap-1 rounded-control bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ $tile['label'] }}</p>
                <p class="truncate text-xl font-semibold text-ink"><bdi dir="ltr">{{ $tile['value'] }}</bdi></p>
            </div>
        @endforeach
    </section>

    <x-ui::chart-card
        id="vendor-orders-by-month"
        :title="__('reporting::vendor_stats.chart_title')"
        :subtitle="$stats['period']"
        type="bar"
        :height="240"
        :ranges="['12m' => ['labels' => $stats['chart']['labels'], 'series' => [['label' => __('reporting::vendor_stats.chart_series'), 'data' => $stats['chart']['data'], 'tone' => 'brand']]]]"
        :empty="array_sum($stats['chart']['data']) === 0"
        :empty-title="__('reporting::vendor_stats.chart_empty_title')"
        :empty-description="__('reporting::vendor_stats.chart_empty_description')"
    >
        @if ($canReports)
            <x-slot:actions>
                <a href="{{ route('admin.reports.show', ['tab' => 'orders', 'tenant_id' => $tenant->id]) }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('reporting::vendor_stats.open_report') }}</a>
            </x-slot:actions>
        @endif
    </x-ui::chart-card>

    <section class="grid gap-3" aria-labelledby="vendor-orders-title">
        <h2 id="vendor-orders-title" class="text-base font-semibold text-ink">{{ __('reporting::vendor_stats.orders_title') }}</h2>
        <x-ui::datatable
            id="vendor-orders"
            :url="route('admin.vendors.stats.orders', $tenant->id)"
            :columns="[
                ['data' => 'order', 'title' => __('reporting::vendor_stats.columns.order')],
                ['data' => 'customer', 'title' => __('reporting::vendor_stats.columns.customer'), 'orderable' => false],
                ['data' => 'date', 'title' => __('reporting::vendor_stats.columns.date')],
                ['data' => 'items', 'title' => __('reporting::vendor_stats.columns.items'), 'orderable' => false, 'align' => 'end'],
                ['data' => 'total', 'title' => __('reporting::vendor_stats.columns.total'), 'align' => 'end'],
                ['data' => 'status_badge', 'title' => __('reporting::vendor_stats.columns.status'), 'orderable' => false],
            ]"
            :order="[[2, 'desc']]"
            :empty-title="__('reporting::vendor_stats.empty_title')"
            :empty-description="__('reporting::vendor_stats.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-orders" :search-placeholder="__('reporting::vendor_stats.search')">
                    <x-ui::select name="status" :label="__('reporting::vendor_stats.status')" label-hidden :options="$stats['statuses']" :placeholder="__('reporting::vendor_stats.status')" :search="false" allow-clear data-filter :data-filter-label="__('reporting::vendor_stats.status')" class="w-40" />
                    <x-ui::daterange name="placed" from-name="from" to-name="to" :label="__('reporting::vendor_stats.date')" label-hidden filter compact class="w-56" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </section>
</div>
