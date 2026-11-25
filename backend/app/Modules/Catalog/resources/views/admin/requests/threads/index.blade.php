{{--
    Requests center › Product requests (ADM-018c, Figma 68:28760) / Manufacturing (ADM-018e,
    68:32218) / Brand requests (ADM-018g, 68:33923): one server-side table per kind with
    search, status filter ("Open" by default for product requests), priority (product),
    archived (product) and the status modal (requests.<kind>.manage).
--}}
@use('App\Modules\Catalog\Enums\RequestKind')
@use('App\Modules\Catalog\Enums\RequestPriority')
@php
    $tabKey = match ($kind) { RequestKind::Product => 'products', RequestKind::Manufacturing => 'manufacturing', RequestKind::Brand => 'brands' };
    $columns = [['data' => 'request', 'title' => __('catalog::requests.columns.request'), 'searchable' => false], ['data' => 'vendor', 'title' => __('catalog::requests.columns.vendor'), 'searchable' => false]];

    if ($kind === RequestKind::Product) {
        $columns[] = ['data' => 'priority', 'title' => __('catalog::requests.columns.priority'), 'searchable' => false];
    }

    if ($kind === RequestKind::Manufacturing) {
        $columns[] = ['data' => 'quantity', 'title' => __('catalog::requests.columns.quantity'), 'searchable' => false, 'align' => 'end'];
    }

    $columns = [
        ...$columns,
        ['data' => 'status', 'title' => __('catalog::requests.columns.status'), 'searchable' => false],
        ['data' => 'unread', 'title' => __('catalog::requests.columns.unread'), 'searchable' => false, 'align' => 'end'],
        ['data' => 'updated', 'title' => __('catalog::requests.columns.updated'), 'searchable' => false],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ];
    $statusOptions = ['open' => __('catalog::requests.filters.open'), ...$statuses];
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::requests.tabs.'.$tabKey)" data-page="admin-requests">
    <div class="grid gap-6" data-requests-list>
        @include('catalog::admin.requests.partials._header', ['tabs' => $tabs, 'active' => $tabKey])

        <x-ui::datatable
            id="requests-{{ $kind->value }}"
            :url="route($kind->routePrefix().'.data')"
            :columns="$columns"
            :order="[[count($columns) - 2, 'desc']]"
            :empty-title="__('catalog::requests.empty.'.$kind->value.'_title')"
            :empty-description="__('catalog::requests.empty.'.$kind->value.'_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="requests-{{ $kind->value }}" :search-placeholder="__('catalog::requests.search.'.$kind->value)">
                    <x-ui::select name="status" :label="__('catalog::requests.filters.status')" label-hidden :options="$statusOptions" :placeholder="__('catalog::requests.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.status')" class="w-44" />
                    @if ($kind === RequestKind::Product)
                        <x-ui::select name="priority" :label="__('catalog::requests.filters.priority')" label-hidden :options="RequestPriority::options()" :placeholder="__('catalog::requests.filters.priority')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.priority')" class="w-36" />
                        <x-ui::select name="archived" :label="__('catalog::requests.filters.archived')" label-hidden :options="['1' => __('catalog::requests.filters.archived')]" :placeholder="__('catalog::requests.filters.archived')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.archived')" class="w-36" />
                    @endif
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    @if ($canManage)
        @include('catalog::admin.requests.threads._status-modal', ['statuses' => $statuses])
    @endif
</x-layouts.panel>
