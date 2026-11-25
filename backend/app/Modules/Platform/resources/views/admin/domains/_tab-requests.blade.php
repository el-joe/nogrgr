{{-- Domains › Domain requests tab (ADM-045): server-side table with status filter + search. --}}
<x-ui::datatable
    id="domain-requests"
    :url="route('admin.vendors.domains.data')"
    :columns="[
        ['data' => 'domain', 'title' => __('platform::domains.columns.domain'), 'searchable' => false],
        ['data' => 'store', 'title' => __('platform::domains.columns.store'), 'searchable' => false],
        ['data' => 'dns', 'title' => __('platform::domains.columns.dns'), 'searchable' => false],
        ['data' => 'ssl', 'title' => __('platform::domains.columns.ssl'), 'searchable' => false],
        ['data' => 'status', 'title' => __('platform::domains.columns.status'), 'searchable' => false],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ]"
    :order="[[0, 'asc']]"
    :empty-title="__('platform::domains.empty_title')"
    :empty-description="__('platform::domains.empty_description')"
>
    <x-slot:filters>
        <x-ui::filters-bar target="domain-requests" :search-placeholder="__('platform::domains.search')">
            <x-ui::select name="status" :label="__('platform::domains.filters.status')" label-hidden :options="$statuses" :placeholder="__('platform::domains.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::domains.filters.status')" class="w-40" />
        </x-ui::filters-bar>
    </x-slot:filters>
</x-ui::datatable>
