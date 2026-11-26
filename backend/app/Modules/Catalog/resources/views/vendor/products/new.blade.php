{{--
    Vendor › Products › New (VEN-011, desktop = client "New in products" grid 12:13819, mobile
    12:21874; cards in _new-card): central products
    offered to the store and not added yet, as cards; select some and "Add selected", or
    "Add all"; each card opens the preview (VEN-011a). Adding = store_products rows only
    (central data is never copied, decision #65).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.products.manage');
@endphp
<x-vendor::layout :title="__('catalog::vendor.new.title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', ['title' => __('catalog::vendor.new.title'), 'subtitle' => trans_choice('catalog::vendor.new.subtitle', $count, ['count' => number_format($count)])])

    <div class="flex flex-wrap items-center justify-between gap-4">
        @include('catalog::vendor.partials._tabs', ['active' => 'new'])
        @if ($canManage && $count > 0)
            <x-ui::button variant="secondary" icon="plus" :data-import-all="vendor_route('catalog.products.new.import')" :data-confirm-title="__('catalog::vendor.new.add_all_title')" :data-confirm-text="trans_choice('catalog::vendor.new.add_all_message', $count, ['count' => number_format($count)])">{{ __('catalog::vendor.new.add_all') }}</x-ui::button>
        @endif
    </div>

    <x-ui::datatable
        id="new-products"
        class="vnd-card-grid"
        :url="vendor_route('catalog.products.new.data')"
        :selectable="$canManage"
        :stack="false"
        :page-length="12"
        :per-page="[12, 24, 48]"
        :columns="[['data' => 'card', 'title' => __('catalog::vendor.new.title'), 'orderable' => false, 'searchable' => false]]"
        :empty-title="__('catalog::vendor.new.empty_title')"
        :empty-description="__('catalog::vendor.new.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="new-products" :search-placeholder="__('catalog::vendor.products.search')">
                <x-ui::select name="category_id" :label="__('catalog::vendor.filters.category')" label-hidden :options="$categoryOptions" :placeholder="__('catalog::vendor.filters.category')" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.category')" class="w-56" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:bulk>
                <x-ui::button size="sm" variant="ghost" icon="plus" data-bulk-import :data-url="vendor_route('catalog.products.new.import')">{{ __('catalog::vendor.new.add_selected') }}</x-ui::button>
            </x-slot:bulk>
        @endif
        <x-slot:empty>
            <x-ui::button variant="secondary" icon="box" :href="vendor_route('catalog.products.index')">{{ __('catalog::vendor.new.back') }}</x-ui::button>
        </x-slot:empty>
    </x-ui::datatable>

    <x-ui::modal id="new-preview-modal" :title="__('catalog::vendor.new.preview_title')" :subtitle="__('catalog::vendor.new.preview_subtitle')">
        @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.new.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor.close') }}</x-ui::button>
            @if ($canManage)
                <x-ui::button icon="plus" data-preview-import :data-url="vendor_route('catalog.products.new.import')" data-remote-submit>{{ __('catalog::vendor.new.add') }}</x-ui::button>
            @endif
        </x-slot:footer>
    </x-ui::modal>
</x-vendor::layout>
