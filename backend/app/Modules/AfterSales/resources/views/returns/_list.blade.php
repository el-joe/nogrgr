{{-- Returns list body shared by ADM-023 and VEN-022: tabs, filters, server-side table. $surface admin|vendor. --}}
@php
    $vendor = $surface === 'vendor';
    $indexUrl = fn (array $query = []) => $vendor ? vendor_route('sales.returns.index', $query) : route('admin.returns.index', $query);
    $tabItems = [];
    foreach ($tabs as $key => $tab) {
        $tabItems[$key] = ['label' => $tab['label'], 'count' => number_format($tab['count']), 'href' => $indexUrl($key === 'all' ? [] : ['tab' => $key])];
    }
    $columns = [
        ['data' => 'return', 'title' => __('after-sales::returns.columns.return')],
        ['data' => 'order', 'title' => __('after-sales::returns.columns.order')],
    ];
    if (! $vendor) {
        $columns[] = ['data' => 'store', 'title' => __('after-sales::returns.columns.store'), 'orderable' => false];
    }
    $columns = [...$columns,
        ['data' => 'item', 'title' => __('after-sales::returns.columns.item'), 'orderable' => false],
        ['data' => 'reason', 'title' => __('after-sales::returns.columns.reason'), 'orderable' => false],
        ['data' => 'value', 'title' => __('after-sales::returns.columns.value'), 'align' => 'end'],
        ['data' => 'status', 'title' => __('after-sales::returns.columns.status')],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ];
@endphp
<div class="grid gap-6">
    <x-ui::page-header :title="__('after-sales::returns.title')" :subtitle="__('after-sales::returns.subtitle_'.$surface)"
        :breadcrumbs="$vendor ? [__('navigation.vendor.orders') => vendor_route('sales.orders.index'), __('after-sales::returns.title') => null] : [__('navigation.sections.sales') => null, __('navigation.admin.returns_refunds') => null, __('after-sales::returns.title') => null]">
        <x-slot:tabs>
            <x-ui::tabs id="returns-tabs" :active="array_key_exists($active, $tabItems) ? $active : 'all'" :label="__('after-sales::returns.title')" :items="$tabItems" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="returns"
        :url="$vendor ? vendor_route('sales.returns.data') : route('admin.returns.data')"
        :columns="$columns"
        :order="[[0, 'desc']]"
        :empty-title="__('after-sales::returns.empty_title')"
        :empty-description="__('after-sales::returns.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="returns" :search-placeholder="__('after-sales::returns.search')">
                @if ($active !== 'all')
                    <input type="hidden" name="tab" value="{{ $active }}" data-filter>
                @endif
                <x-ui::select name="status" :label="__('after-sales::returns.filters.status')" label-hidden :options="$statuses" :placeholder="__('after-sales::returns.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('after-sales::returns.filters.status')" class="w-44" />
                <x-ui::select name="type" :label="__('after-sales::returns.filters.type')" label-hidden :options="$types" :placeholder="__('after-sales::returns.filters.type')" :search="false" allow-clear data-filter :data-filter-label="__('after-sales::returns.filters.type')" class="w-36" />
                @unless ($vendor)
                    <x-ui::select name="tenant_id" :label="__('after-sales::returns.filters.store')" label-hidden :options="[]" :placeholder="__('after-sales::returns.filters.store')" :ajax-url="route('admin.returns.stores')" allow-clear data-filter :data-filter-label="__('after-sales::returns.filters.store')" class="w-48" />
                @endunless
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</div>
