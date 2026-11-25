{{--
    Own products section page (VEN-016a, Tenant Panel Layout 12:13819): every own product of
    one section as a server-side card grid with search. Data: $section (OwnProductSection).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.own-products.manage');
    $actions = $canManage ? view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.own-products.create'), 'icon' => 'plus', 'label' => __('catalog::vendor.own.add'), 'variant' => 'primary'])->render() : null;
@endphp
<x-vendor::layout :title="$section->label()" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', array_filter(['title' => $section->label(), 'subtitle' => __('catalog::vendor.own.section_subtitle'), 'actions' => $actions]))

    <x-ui::datatable
        id="own-products"
        class="vnd-card-grid"
        :url="vendor_route('catalog.own-products.data', ['section' => $section->value])"
        :stack="false"
        :page-length="12"
        :per-page="[12, 24, 48]"
        :columns="[['data' => 'card', 'title' => $section->label(), 'orderable' => false, 'searchable' => false]]"
        :empty-title="__('catalog::vendor.own.section_empty.'.$section->value)"
        :empty-description="__('catalog::vendor.own.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="own-products" :search-placeholder="__('catalog::vendor.products.search')">
                <x-ui::select name="status" :label="__('catalog::vendor.filters.status')" label-hidden :options="\App\Modules\Catalog\Enums\StoreProductFilter::options()" :placeholder="__('catalog::vendor.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.status')" class="w-44" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-vendor::layout>
