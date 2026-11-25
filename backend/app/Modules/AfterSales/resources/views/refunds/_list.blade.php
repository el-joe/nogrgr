{{-- Refunds list body shared by ADM-024 (Figma 70:38484) and VEN-023 (84:105181). $surface admin|vendor. --}}
@php
    $vendor = $surface === 'vendor';
    $indexUrl = fn (array $query = []) => $vendor ? vendor_route('sales.refunds.index', $query) : route('admin.refunds.index', $query);
    $counts = $stats['counts'];
    $tabItems = ['all' => ['label' => __('after-sales::refunds.tabs.all'), 'href' => $indexUrl()]];
    foreach (['pending', 'failed', 'completed', 'rejected'] as $status) {
        $tabItems[$status] = ['label' => \App\Modules\AfterSales\Enums\RefundStatus::from($status)->label(), 'href' => $indexUrl(['status' => $status])] + (in_array($status, ['pending', 'failed'], true) ? ['count' => number_format($status === 'pending' ? $stats['pending_count'] : $stats['failed_count'])] : []);
    }
    $columns = [
        ['data' => 'refund', 'title' => __('after-sales::refunds.columns.refund')],
        ['data' => 'order', 'title' => __('after-sales::refunds.columns.order')],
    ];
    if (! $vendor) {
        $columns[] = ['data' => 'store', 'title' => __('after-sales::refunds.columns.store'), 'orderable' => false];
    }
    $columns = [...$columns,
        ['data' => 'source', 'title' => __('after-sales::refunds.columns.source')],
        ['data' => 'gateway', 'title' => __('after-sales::refunds.columns.gateway')],
        ['data' => 'amount', 'title' => __('after-sales::refunds.columns.amount'), 'align' => 'end'],
        ['data' => 'status', 'title' => __('after-sales::refunds.columns.status')],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ];
    $total = array_sum($counts);
@endphp
<div class="grid gap-6">
    <x-ui::page-header :title="__('after-sales::refunds.title')" :subtitle="__('after-sales::refunds.subtitle_'.$surface)"
        :breadcrumbs="$vendor ? [__('navigation.vendor.orders') => vendor_route('sales.orders.index'), __('after-sales::refunds.title') => null] : [__('navigation.sections.sales') => null, __('navigation.admin.returns_refunds') => null, __('after-sales::refunds.title') => null]">
        @unless ($vendor)
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.refunds.export', request()->only('status'))" data-export-link>{{ __('after-sales::refunds.export') }}</x-ui::button>
            </x-slot:actions>
        @endunless
        <x-slot:tabs>
            <x-ui::tabs id="refunds-tabs" :active="$active" :label="__('after-sales::refunds.title')" :items="$tabItems" />
        </x-slot:tabs>
    </x-ui::page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui::kpi-card :label="__('after-sales::refunds.kpi.refunded')" :value="\App\Shared\Money\Money::ofMinor($stats['refunded_30d'])->format()" icon="return" :comparison="__('after-sales::refunds.kpi.refunded_hint')" />
        <x-ui::kpi-card :label="__('after-sales::refunds.kpi.pending')" :value="\App\Shared\Money\Money::ofMinor($stats['pending'])->format()" icon="clock" :comparison="trans_choice('after-sales::refunds.kpi.count', $stats['pending_count'], ['count' => $stats['pending_count']])" />
        <x-ui::kpi-card :label="__('after-sales::refunds.kpi.failed')" :value="\App\Shared\Money\Money::ofMinor($stats['failed'])->format()" icon="alert-triangle" :comparison="trans_choice('after-sales::refunds.kpi.count', $stats['failed_count'], ['count' => $stats['failed_count']])" />
        <x-ui::kpi-card :label="__('after-sales::refunds.kpi.total')" :value="number_format($total)" icon="percent" :comparison="__('after-sales::refunds.kpi.total_hint')" />
    </div>

    <x-ui::datatable
        id="refunds"
        :url="$vendor ? vendor_route('sales.refunds.data') : route('admin.refunds.data')"
        :columns="$columns"
        :order="[[0, 'desc']]"
        :empty-title="__('after-sales::refunds.empty_title')"
        :empty-description="__('after-sales::refunds.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="refunds" :search-placeholder="__('after-sales::refunds.search')">
                @if ($active !== 'all')
                    <input type="hidden" name="status" value="{{ $active }}" data-filter>
                @endif
                @unless ($vendor)
                    <x-ui::select name="tenant_id" :label="__('after-sales::refunds.filters.store')" label-hidden :options="[]" :placeholder="__('after-sales::refunds.filters.store')" :ajax-url="route('admin.refunds.stores')" allow-clear data-filter :data-filter-label="__('after-sales::refunds.filters.store')" class="w-48" />
                @endunless
                <x-ui::select name="source" :label="__('after-sales::refunds.filters.source')" label-hidden :options="$sources" :placeholder="__('after-sales::refunds.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('after-sales::refunds.filters.source')" class="w-40" />
                <x-ui::daterange name="requested" from-name="from" to-name="to" :label="__('after-sales::refunds.filters.date')" label-hidden filter compact class="w-56" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</div>

<x-ui::modal id="refund-modal" :title="__('after-sales::refunds.title')">
    @include('commerce::admin.partials.remote-states')
</x-ui::modal>
