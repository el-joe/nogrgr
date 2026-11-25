{{--
    Admin › Sales › Promotions › Flash sales (ADM-026, Figma Admin Panel 70:40462): Live /
    Scheduled / Ended tabs with counts, server-side table (title + banner, discount,
    products, countries, ends, status), search, country + on/off filters, view / edit in
    the flash sale modal (ADM-026a) and delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('flash-sales.manage');
    $tabItems = [];
    foreach (['live', 'scheduled', 'ended'] as $key) {
        $tabItems[$key] = ['label' => __('commerce::flash_sales.tabs.'.$key), 'href' => route('admin.promotions.flash-sales.index', ['phase' => $key])] + ($key !== 'ended' ? ['count' => $counts[$key]] : []);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('commerce::flash_sales.title')">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('commerce::flash_sales.title')"
            :subtitle="__('commerce::flash_sales.subtitle')"
            :breadcrumbs="[__('navigation.sections.sales') => null, __('navigation.admin.promotions') => null, __('commerce::flash_sales.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="flash-sale-modal" :data-url="route('admin.promotions.flash-sales.create')">{{ __('commerce::flash_sales.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="flash-sale-tabs" :active="$phase" :label="__('commerce::flash_sales.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="flash-sales"
            :url="route('admin.promotions.flash-sales.data')"
            :columns="[
                ['data' => 'title', 'title' => __('commerce::flash_sales.columns.title'), 'searchable' => false],
                ['data' => 'discount_percentage', 'title' => __('commerce::flash_sales.columns.discount'), 'searchable' => false],
                ['data' => 'products_count', 'title' => __('commerce::flash_sales.columns.products'), 'align' => 'end', 'searchable' => false],
                ['data' => 'countries', 'title' => __('commerce::flash_sales.columns.countries'), 'orderable' => false, 'searchable' => false],
                ['data' => 'ends_at', 'title' => __('commerce::flash_sales.columns.ends'), 'searchable' => false],
                ['data' => 'status', 'title' => __('commerce::flash_sales.columns.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, $phase === 'ended' ? 'desc' : 'asc']]"
            :empty-title="__('commerce::flash_sales.empty.'.$phase.'.title')"
            :empty-description="__('commerce::flash_sales.empty.'.$phase.'.description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="flash-sales" :search-placeholder="__('commerce::flash_sales.search')">
                    <input type="hidden" name="phase" value="{{ $phase }}" data-filter>
                    <x-ui::select name="country_id" :label="__('commerce::flash_sales.filters.country')" label-hidden :options="$countries" :placeholder="__('commerce::flash_sales.filters.country')" allow-clear data-filter :data-filter-label="__('commerce::flash_sales.filters.country')" class="w-48" />
                    <x-ui::select name="status" :label="__('commerce::flash_sales.filters.status')" label-hidden :options="['active' => __('commerce::flash_sales.filters.on'), 'inactive' => __('commerce::flash_sales.filters.off')]" :placeholder="__('commerce::flash_sales.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::flash_sales.filters.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="flash-sale-modal" :data-url="route('admin.promotions.flash-sales.create')">{{ __('commerce::flash_sales.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @if ($canManage)
        <x-ui::modal id="flash-sale-modal" :title="__('commerce::flash_sales.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="flash-sale-form" icon="check" data-remote-submit>{{ __('commerce::promotions.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

    <x-ui::modal id="flash-sale-view-modal" :title="__('commerce::flash_sales.title')">
        @include('commerce::admin.partials.remote-states')
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
