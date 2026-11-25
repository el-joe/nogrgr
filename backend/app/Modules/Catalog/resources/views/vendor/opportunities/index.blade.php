{{--
    Today's chances (VEN-015, Tenant Panel Layout 12:12050, mobile 12:18958): central
    products ranked by sales and recency as opportunity cards; "Add to store" opens the
    select markets modal (VEN-015a), products already in the store show "Already added"
    and open the markets view (VEN-015b). Filters: search, category, not added yet.
--}}
<x-vendor::layout :title="__('catalog::vendor.opportunities.title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', ['subtitle' => __('catalog::vendor.opportunities.subtitle')])

    <x-ui::datatable
        id="opportunities"
        class="vnd-card-grid"
        :url="vendor_route('catalog.opportunities.data')"
        :stack="false"
        :page-length="12"
        :per-page="[12, 24, 48]"
        :columns="[['data' => 'card', 'title' => __('catalog::vendor.opportunities.title'), 'orderable' => false, 'searchable' => false]]"
        :empty-title="__('catalog::vendor.opportunities.empty_title')"
        :empty-description="__('catalog::vendor.opportunities.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="opportunities" :search-placeholder="__('catalog::vendor.products.search')">
                <x-ui::select name="category_id" :label="__('catalog::vendor.filters.category')" label-hidden :options="$categoryOptions" :placeholder="__('catalog::vendor.filters.category')" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.category')" class="w-56" />
                <x-ui::select name="added" :label="__('catalog::vendor.opportunities.filter_added')" label-hidden :options="['no' => __('catalog::vendor.opportunities.not_added')]" :placeholder="__('catalog::vendor.opportunities.filter_added')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor.opportunities.filter_added')" class="w-48" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>

    <x-ui::modal id="markets-modal" :title="__('catalog::vendor.opportunities.markets_title')" :subtitle="__('catalog::vendor.opportunities.markets_subtitle')">
        @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.opportunities.load_error')])
    </x-ui::modal>
</x-vendor::layout>
