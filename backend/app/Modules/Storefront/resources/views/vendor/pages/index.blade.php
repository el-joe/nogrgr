{{--
    Vendor › Your store › Pages (VEN-033, Figma Vendor Panel 85:106165): "New page", search +
    status / type filters, list (page title + /pages/slug with a System badge, published
    and footer switches, updated, actions: view on store, edit, delete custom pages).
    Create / edit = VEN-033a full page (language tabs, slug, rich-text body, meta).
--}}
<x-vendor::layout :title="__('content::store_pages.title')" page="vendor-store-pages">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('content::store_pages.title')" :subtitle="__('content::store_pages.subtitle')" :breadcrumbs="[__('content::store_pages.crumb') => null, __('content::store_pages.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="vendor_route('store.pages.create')">{{ __('content::store_pages.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-store-pages"
            :url="vendor_route('store.pages.data')"
            :columns="[
                ['data' => 'page', 'title' => __('content::store_pages.columns.page')],
                ['data' => 'status', 'title' => __('content::store_pages.columns.status')],
                ['data' => 'footer', 'title' => __('content::store_pages.columns.footer')],
                ['data' => 'updated', 'title' => __('content::store_pages.columns.updated')],
                ['data' => 'actions', 'title' => __('content::store_pages.columns.actions'), 'orderable' => false, 'searchable' => false, 'align' => 'end'],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('content::store_pages.empty_title')"
            :empty-description="__('content::store_pages.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-store-pages" :search-placeholder="__('content::store_pages.search')">
                    <x-ui::select name="status" :label="__('content::store_pages.filters.status')" label-hidden :options="$statuses" :placeholder="__('content::store_pages.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('content::store_pages.filters.status')" class="w-40" />
                    <x-ui::select name="type" :label="__('content::store_pages.filters.type')" label-hidden :options="$types" :placeholder="__('content::store_pages.filters.type')" :search="false" allow-clear data-filter :data-filter-label="__('content::store_pages.filters.type')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="vendor_route('store.pages.create')">{{ __('content::store_pages.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-vendor::layout>
