{{--
    Vendor › Newsletter subscribers (VEN-026, Figma Vendor Panel 84:106555): Export CSV,
    e-mail search + language / status / source filters, list (e-mail, language, source,
    status, subscribed), view the linked customer, unsubscribe / re-subscribe and delete.
--}}
@php $staff = auth('vendor')->user(); @endphp
<x-vendor::layout :title="__('engagement::subscribers.title')" page="vendor-customers">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('engagement::subscribers.title')" :subtitle="__('engagement::subscribers.subtitle')" :breadcrumbs="[__('engagement::subscribers.section') => null, __('engagement::subscribers.breadcrumb') => null]">
            @if ($staff?->can('subscribers.export'))
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="download" :href="vendor_route('sales.subscribers.export')">{{ __('engagement::subscribers.export') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-subscribers"
            :url="vendor_route('sales.subscribers.data')"
            :columns="[
                ['data' => 'email', 'title' => __('engagement::subscribers.columns.email')],
                ['data' => 'language', 'title' => __('engagement::subscribers.columns.language')],
                ['data' => 'source', 'title' => __('engagement::subscribers.columns.source')],
                ['data' => 'status', 'title' => __('engagement::subscribers.columns.status')],
                ['data' => 'subscribed', 'title' => __('engagement::subscribers.columns.subscribed')],
                ['data' => 'actions', 'title' => __('engagement::subscribers.columns.actions'), 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('engagement::subscribers.empty_title')"
            :empty-description="__('engagement::subscribers.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-subscribers" :search-placeholder="__('engagement::subscribers.search')">
                    <x-ui::select name="locale" :label="__('engagement::subscribers.filters.language')" label-hidden :options="$locales" :placeholder="__('engagement::subscribers.filters.language')" :search="false" allow-clear data-filter :data-filter-label="__('engagement::subscribers.filters.language')" class="w-40" />
                    <x-ui::select name="status" :label="__('engagement::subscribers.filters.status')" label-hidden :options="$statuses" :placeholder="__('engagement::subscribers.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('engagement::subscribers.filters.status')" class="w-40" />
                    <x-ui::select name="source" :label="__('engagement::subscribers.filters.source')" label-hidden :options="$sources" :placeholder="__('engagement::subscribers.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('engagement::subscribers.filters.source')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-vendor::layout>
