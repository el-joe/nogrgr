{{--
    Admin › Catalog › Attributes (ADM-015, Figma Admin Panel 65:25447): server-side table
    (attribute, type, options, used by), search + type filter, edit and delete.
--}}
@php($canManage = (bool) auth('admin')->user()?->can('catalog.attributes.manage'))
<x-layouts.panel surface="admin" :title="__('catalog::attributes.title')">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::attributes.title')"
            :subtitle="__('catalog::attributes.subtitle')"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::attributes.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="route('admin.catalog.attributes.create')">{{ __('catalog::attributes.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="attributes"
            :url="route('admin.catalog.attributes.data')"
            :columns="[
                ['data' => 'name', 'title' => __('catalog::attributes.columns.attribute')],
                ['data' => 'display_type', 'title' => __('catalog::attributes.columns.type')],
                ['data' => 'options', 'title' => __('catalog::attributes.columns.options')],
                ['data' => 'products_count', 'title' => __('catalog::attributes.columns.used_by'), 'align' => 'end', 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('catalog::attributes.empty_title')"
            :empty-description="__('catalog::attributes.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="attributes" :search-placeholder="__('catalog::attributes.search')">
                    <x-ui::select name="display_type" :label="__('catalog::attributes.columns.type')" label-hidden :options="\App\Modules\Catalog\Enums\AttributeDisplayType::options()" :placeholder="__('catalog::attributes.columns.type')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::attributes.columns.type')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" :href="route('admin.catalog.attributes.create')">{{ __('catalog::attributes.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>
</x-layouts.panel>
