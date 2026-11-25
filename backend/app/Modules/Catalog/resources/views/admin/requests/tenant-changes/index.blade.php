{{--
    Requests center › Tenant changes (ADM-018i, Figma 68:34678): selling countries /
    categories change requests with type + status filters; rows open the review page.
--}}
<x-layouts.panel surface="admin" :title="__('catalog::requests.tabs.tenant-changes')" data-page="admin-requests">
    <div class="grid gap-6" data-requests-list>
        @include('catalog::admin.requests.partials._header', ['tabs' => $tabs, 'active' => 'tenant-changes'])

        <x-ui::datatable
            id="requests-tenant-changes"
            :url="route('admin.requests.tenant-changes.data')"
            :columns="[
                ['data' => 'vendor', 'title' => __('catalog::requests.columns.vendor'), 'searchable' => false],
                ['data' => 'type', 'title' => __('catalog::requests.columns.type'), 'searchable' => false],
                ['data' => 'change', 'title' => __('catalog::requests.columns.change'), 'searchable' => false, 'orderable' => false],
                ['data' => 'status', 'title' => __('catalog::requests.columns.status'), 'searchable' => false],
                ['data' => 'submitted', 'title' => __('catalog::requests.columns.submitted'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('catalog::requests.empty.tenant_changes_title')"
            :empty-description="__('catalog::requests.empty.tenant_changes_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="requests-tenant-changes" :search-placeholder="__('catalog::requests.search.tenant_changes')">
                    <x-ui::select name="type" :label="__('catalog::requests.filters.type')" label-hidden :options="$types" :placeholder="__('catalog::requests.filters.type')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.type')" class="w-36" />
                    <x-ui::select name="status" :label="__('catalog::requests.filters.status')" label-hidden :options="$statuses" :placeholder="__('catalog::requests.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::requests.filters.status')" class="w-36" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
