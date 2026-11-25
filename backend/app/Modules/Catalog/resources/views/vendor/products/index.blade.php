{{--
    Vendor › Products (VEN-010, Tenant Panel Layout 12:12968, mobile 12:21178): welcome
    header, Products / Own products / New pill tabs, Add product, server-side card grid of
    the store's imported central products (search, status, category), CSV export, the
    price list modal (VEN-010a), shipping details modal (VEN-010e) and the request-change
    modal (VEN-013a). Selected cards can get one markup at once (queued run).
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('catalog.products.manage');
    $canExport = (bool) $staff?->can('catalog.products.export');
    $canOwn = (bool) $staff?->can('catalog.own-products.manage');
    $actions = '';
    if ($canExport) {
        $actions .= view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.products.export'), 'icon' => 'download', 'label' => __('catalog::vendor.products.export'), 'variant' => 'outline', 'attributes' => 'data-export-for="store-products"'])->render();
    }
    if ($canOwn) {
        $actions .= view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.own-products.create'), 'icon' => 'plus', 'label' => __('catalog::vendor.own.add'), 'variant' => 'primary'])->render();
    }
@endphp
<x-vendor::layout :title="__('catalog::vendor.products.title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', ['actions' => $actions])

    <div class="flex flex-wrap items-center justify-between gap-4">
        @include('catalog::vendor.partials._tabs', ['active' => 'products'])
        @if ($newCount > 0)
            <a href="{{ vendor_route('catalog.products.new') }}" class="inline-flex items-center gap-2 rounded-full bg-primary-subtle px-4 py-2 text-sm font-medium text-ink-brand hover:bg-primary hover:text-on-primary">
                <x-ui::icon name="sparkles" :size="18" />{{ trans_choice('catalog::vendor.new.banner', $newCount, ['count' => number_format($newCount)]) }}
            </a>
        @endif
    </div>

    <x-ui::datatable
        id="store-products"
        class="vnd-card-grid"
        :url="vendor_route('catalog.products.data')"
        :selectable="$canManage"
        :stack="false"
        :page-length="12"
        :per-page="[12, 24, 48]"
        :columns="[['data' => 'card', 'title' => __('catalog::vendor.products.title'), 'orderable' => false, 'searchable' => false]]"
        :empty-title="__('catalog::vendor.products.empty_title')"
        :empty-description="__('catalog::vendor.products.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="store-products" :search-placeholder="__('catalog::vendor.products.search')">
                <x-ui::select name="status" :label="__('catalog::vendor.filters.status')" label-hidden :options="\App\Modules\Catalog\Enums\StoreProductFilter::options()" :placeholder="__('catalog::vendor.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.status')" class="w-44" />
                <x-ui::select name="category_id" :label="__('catalog::vendor.filters.category')" label-hidden :options="$categoryOptions" :placeholder="__('catalog::vendor.filters.category')" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.category')" class="w-56" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:bulk>
                <x-ui::button size="sm" variant="ghost" icon="percent" data-bulk-markup data-overlay-open="bulk-markup-modal">{{ __('catalog::vendor.price_list.bulk') }}</x-ui::button>
            </x-slot:bulk>
        @endif
        <x-slot:empty>
            <x-ui::button icon="plus" :href="vendor_route('catalog.products.new')">{{ __('catalog::vendor.new.browse') }}</x-ui::button>
        </x-slot:empty>
    </x-ui::datatable>

    @include('catalog::vendor.products._modals', ['canManage' => $canManage])
</x-vendor::layout>
