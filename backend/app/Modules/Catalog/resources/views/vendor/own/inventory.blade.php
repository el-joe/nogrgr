{{--
    Own products inventory (VEN-016b, Tenant Panel Layout 12:17880, mobile 12:23071): card grid
    with inline stock editing (per variant when the product has variants).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.own-products.manage');
    $actions = $canManage ? view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.own-products.create'), 'icon' => 'plus', 'label' => __('catalog::vendor.own.add'), 'variant' => 'primary'])->render() : null;
@endphp
<x-vendor::layout :title="__('catalog::vendor.own.inventory_title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', array_filter(['title' => __('catalog::vendor.own.inventory_title'), 'subtitle' => __('catalog::vendor.own.inventory_subtitle'), 'actions' => $actions]))

    <x-ui::datatable
        id="own-inventory"
        class="vnd-card-grid"
        :url="vendor_route('catalog.own-products.data', ['view' => 'inventory'])"
        :stack="false"
        :page-length="12"
        :per-page="[12, 24, 48]"
        :columns="[['data' => 'card', 'title' => __('catalog::vendor.own.inventory_title'), 'orderable' => false, 'searchable' => false]]"
        :empty-title="__('catalog::vendor.own.empty_title')"
        :empty-description="__('catalog::vendor.own.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="own-inventory" :search-placeholder="__('catalog::vendor.products.search')">
                <x-ui::select name="status" :label="__('catalog::vendor.filters.status')" label-hidden :options="\App\Modules\Catalog\Enums\StoreProductFilter::options()" :placeholder="__('catalog::vendor.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.status')" class="w-44" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="vendor_route('catalog.own-products.create')">{{ __('catalog::vendor.own.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-vendor::layout>
