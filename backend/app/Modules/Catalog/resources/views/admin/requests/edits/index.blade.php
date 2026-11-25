{{--
    Requests center › Product edit requests (ADM-018a, Figma 68:29747): server-side table
    with search and status filter; rows open the diff page.
--}}
<x-layouts.panel surface="admin" :title="__('catalog::requests.tabs.edits')" data-page="admin-requests">
    <div class="grid gap-6" data-requests-list>
        @include('catalog::admin.requests.partials._header', ['tabs' => $tabs, 'active' => 'edits'])

        <x-ui::datatable
            id="requests-edits"
            :url="route('admin.requests.edits.data')"
            :columns="[
                ['data' => 'request', 'title' => __('catalog::requests.columns.request'), 'searchable' => false],
                ['data' => 'vendor', 'title' => __('catalog::requests.columns.vendor'), 'searchable' => false],
                ['data' => 'fields', 'title' => __('catalog::requests.columns.fields'), 'searchable' => false, 'orderable' => false],
                ['data' => 'status', 'title' => __('catalog::requests.columns.status'), 'searchable' => false],
                ['data' => 'submitted', 'title' => __('catalog::requests.columns.submitted'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('catalog::requests.empty.edits_title')"
            :empty-description="__('catalog::requests.empty.edits_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="requests-edits" :search-placeholder="__('catalog::requests.search.edits')">
                    <x-ui::select name="status" :label="__('catalog::requests.filters.status')" label-hidden :options="$statuses" :placeholder="__('catalog::requests.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
