{{--
    Admin › Catalog › Products (ADM-010, Figma Admin Panel 44:13636): tabs with counts
    (All, Active, Draft, Archived, Out of stock, Trash), server-side table with search
    and status / category / assignment / stock filters, price list modal (ADM-010b),
    edit, delete, restore and CSV export of the filtered list.
--}}
@php
    $user = auth('admin')->user();
    $canManage = (bool) $user?->can('catalog.products.manage');
    $canBulk = $canManage && $tab !== \App\Modules\Catalog\Enums\ProductListTab::Trash;
    $canExport = (bool) $user?->can('catalog.products.export');
    $tabItems = [];
    foreach (\App\Modules\Catalog\Enums\ProductListTab::cases() as $case) {
        $tabItems[$case->value] = ['label' => $case->label(), 'count' => number_format($counts[$case->value] ?? 0), 'href' => route('admin.catalog.products.index', $case === \App\Modules\Catalog\Enums\ProductListTab::All ? [] : ['tab' => $case->value])];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::products.title')" data-page="admin-products">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::products.title')"
            :subtitle="trans_choice('catalog::products.subtitle', $counts['all'], ['count' => number_format($counts['all'])])"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::products.title') => null]"
        >
            <x-slot:actions>
                @if ($canExport)
                    <x-ui::button variant="secondary" icon="download" :href="route('admin.catalog.products.export', ['tab' => $tab->value])" data-export-for="products">{{ __('catalog::products.export') }}</x-ui::button>
                @endif
                @if ($canManage)
                    <x-ui::button icon="plus" :href="route('admin.catalog.products.create')">{{ __('catalog::products.add') }}</x-ui::button>
                @endif
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="product-tabs" variant="segmented" :active="$tab->value" :label="__('catalog::products.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="products"
            :url="route('admin.catalog.products.data', ['tab' => $tab->value])"
            :selectable="$canBulk"
            :columns="[
                ['data' => 'name', 'title' => __('catalog::products.columns.product')],
                ['data' => 'category', 'title' => __('catalog::products.columns.category'), 'orderable' => false, 'searchable' => false],
                ['data' => 'price', 'title' => __('catalog::products.columns.price'), 'align' => 'end', 'searchable' => false],
                ['data' => 'stock', 'title' => __('catalog::products.columns.stock'), 'align' => 'end', 'searchable' => false],
                ['data' => 'status', 'title' => __('catalog::products.columns.status'), 'searchable' => false],
                ['data' => 'updated_at', 'title' => __('catalog::products.columns.updated'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[$canBulk ? 6 : 5, 'desc']]"
            :empty-title="__('catalog::products.empty_title')"
            :empty-description="__('catalog::products.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="products" :search-placeholder="__('catalog::products.search')">
                    @if (! in_array($tab, [\App\Modules\Catalog\Enums\ProductListTab::Published, \App\Modules\Catalog\Enums\ProductListTab::Draft, \App\Modules\Catalog\Enums\ProductListTab::Archived], true))
                        <x-ui::select name="status" :label="__('catalog::products.filters.status')" label-hidden :options="\App\Modules\Catalog\Enums\ProductStatus::options()" :placeholder="__('catalog::products.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::products.filters.status')" class="w-40" />
                    @endif
                    <x-ui::select name="category_id" :label="__('catalog::products.filters.category')" label-hidden :options="$categoryOptions" :placeholder="__('catalog::products.filters.category')" allow-clear data-filter :data-filter-label="__('catalog::products.filters.category')" class="w-48" />
                    <x-ui::select name="scope" :label="__('catalog::products.filters.scope')" label-hidden :options="\App\Modules\Catalog\Enums\AssignmentScope::options()" :placeholder="__('catalog::products.filters.scope')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::products.filters.scope')" class="w-48" />
                    <x-ui::select name="stock" :label="__('catalog::products.filters.stock')" label-hidden :options="\App\Modules\Catalog\Enums\StockState::options()" :placeholder="__('catalog::products.filters.stock')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::products.filters.stock')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canBulk)
                <x-slot:bulk>
                    <x-ui::button size="sm" variant="ghost" icon="check" data-bulk-action="activate" :data-url="route('admin.catalog.products.bulk')">{{ __('catalog::products.bulk.activate') }}</x-ui::button>
                    <x-ui::button size="sm" variant="ghost" icon="x" data-bulk-action="deactivate" :data-url="route('admin.catalog.products.bulk')">{{ __('catalog::products.bulk.deactivate') }}</x-ui::button>
                    <x-ui::button size="sm" variant="danger" icon="trash" data-bulk-action="delete" :data-url="route('admin.catalog.products.bulk')" :data-bulk-confirm="__('catalog::products.bulk.delete_message')" :data-bulk-confirm-title="__('catalog::products.bulk.delete_title')">{{ __('catalog::products.bulk.delete') }}</x-ui::button>
                </x-slot:bulk>
            @endif
            @if ($canManage && $tab !== \App\Modules\Catalog\Enums\ProductListTab::Trash)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.catalog.products.create')">{{ __('catalog::products.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    <x-ui::modal id="price-list-modal" :title="__('catalog::products.price_list')" :subtitle="__('catalog::products.prices.subtitle')" size="lg">
        @include('catalog::admin.partials.remote-states', ['error' => __('catalog::products.prices.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::products.prices.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
