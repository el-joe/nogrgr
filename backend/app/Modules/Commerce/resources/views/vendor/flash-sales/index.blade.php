{{--
    Vendor › Ads & Marketing › Flash sales (VEN-041, Figma Tenant Panel 87:108154): "My
    flash sales" / "Nogrgr flash sales (read-only)" tabs with counts, server-side table
    (title + banner, discount, products, ends, status), search + status filter, view /
    edit in the flash sale modal (VEN-041a) and delete; central rows hide / show only;
    the note under the table names the platform sales running in the store.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('flash-sales.manage');
    $central = $tab === 'central';
    $tabItems = [
        'own' => ['label' => __('commerce::vendor_promotions.tabs.own_sales'), 'href' => vendor_route('marketing.flash-sales.index'), 'count' => number_format($counts['own'])],
        'central' => ['label' => __('commerce::vendor_promotions.tabs.central_sales'), 'href' => vendor_route('marketing.flash-sales.index', ['tab' => 'central']), 'count' => number_format($counts['central'])],
    ];
@endphp
<x-vendor::layout :title="__('commerce::flash_sales.title')" page="vendor-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('commerce::flash_sales.title')"
            :subtitle="__('commerce::vendor_promotions.flash_sales.subtitle')"
            :breadcrumbs="[__('commerce::vendor_promotions.section') => null, __('commerce::flash_sales.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="vendor-flash-sale-modal" :data-url="vendor_route('marketing.flash-sales.create')">{{ __('commerce::flash_sales.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="vendor-flash-sale-tabs" :active="$tab" :label="__('commerce::flash_sales.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-flash-sales"
            :url="vendor_route('marketing.flash-sales.data')"
            :columns="[
                ['data' => 'title', 'title' => __('commerce::flash_sales.columns.title'), 'searchable' => false],
                ['data' => 'discount', 'title' => __('commerce::flash_sales.columns.discount'), 'searchable' => false],
                ['data' => 'products', 'title' => __('commerce::flash_sales.columns.products'), 'align' => 'end', 'searchable' => false],
                ['data' => 'ends', 'title' => __('commerce::flash_sales.columns.ends'), 'searchable' => false],
                ['data' => 'status', 'title' => __('commerce::flash_sales.columns.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[3, 'asc']]"
            :empty-title="__('commerce::vendor_promotions.flash_sales.empty_'.$tab.'_title')"
            :empty-description="__('commerce::vendor_promotions.flash_sales.empty_'.$tab.'_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-flash-sales" :search-placeholder="__('commerce::flash_sales.search')">
                    <input type="hidden" name="tab" value="{{ $tab }}" data-filter>
                    <x-ui::select name="status" :label="__('commerce::vendor_promotions.filters.status')" label-hidden :options="$statuses" :placeholder="__('commerce::vendor_promotions.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::vendor_promotions.filters.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage && ! $central)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="vendor-flash-sale-modal" :data-url="vendor_route('marketing.flash-sales.create')">{{ __('commerce::flash_sales.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>

        <p class="flex items-start gap-2 rounded-card bg-info-bg px-4 py-3 text-[13px] text-info">
            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ $centralTitles === [] ? __('commerce::vendor_promotions.flash_sales.central_note_empty') : __('commerce::vendor_promotions.flash_sales.central_note', ['names' => implode(', ', $centralTitles)]) }}</span>
        </p>
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-flash-sale-modal" :title="__('commerce::flash_sales.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-flash-sale-form" data-remote-submit>{{ __('commerce::promotions.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

    <x-ui::modal id="vendor-flash-sale-view-modal" :title="__('commerce::flash_sales.title')">
        @include('commerce::admin.partials.remote-states')
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-vendor::layout>
