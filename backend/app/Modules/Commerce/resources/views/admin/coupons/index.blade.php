{{--
    Admin › Sales › Promotions › Coupons (ADM-025, Figma Admin Panel 70:39532): country
    tabs (coupons usable there), server-side table (code + reach, discount, min spend,
    usage in all stores, dates, status), code search, status + type filters, view / edit
    in the coupon modal (ADM-025a) and delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('coupons.manage');
    $country = filter_var(request()->query('country_id'), FILTER_VALIDATE_INT);
    $active = $country !== false && array_key_exists($country, $tabs) ? (string) $country : 'all';
    $tabItems = ['all' => ['label' => __('commerce::coupons.tabs.all'), 'href' => route('admin.promotions.coupons.index')]];
    foreach ($tabs as $id => $name) {
        $tabItems[(string) $id] = ['label' => $name, 'href' => route('admin.promotions.coupons.index', ['country_id' => $id])];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('commerce::coupons.title')">
    <div class="contents" data-page="admin-promotions">
        <div class="grid gap-6">
            <x-ui::page-header
                :title="__('commerce::coupons.title')"
                :subtitle="__('commerce::coupons.subtitle')"
                :breadcrumbs="[__('navigation.sections.sales') => null, __('navigation.admin.promotions') => null, __('commerce::coupons.title') => null]"
            >
                @if ($canManage)
                    <x-slot:actions>
                        <x-ui::button icon="plus" data-remote-modal="coupon-modal" :data-url="route('admin.promotions.coupons.create')">{{ __('commerce::coupons.add') }}</x-ui::button>
                    </x-slot:actions>
                @endif
                <x-slot:tabs>
                    <x-ui::tabs id="coupon-country-tabs" :active="$active" :label="__('commerce::coupons.title')" :items="$tabItems" />
                </x-slot:tabs>
            </x-ui::page-header>

            <x-ui::datatable
                id="coupons"
                :url="route('admin.promotions.coupons.data')"
                :columns="[
                    ['data' => 'code', 'title' => __('commerce::coupons.columns.code'), 'searchable' => false],
                    ['data' => 'discount', 'title' => __('commerce::coupons.columns.discount'), 'searchable' => false],
                    ['data' => 'min_spend', 'title' => __('commerce::coupons.columns.min_spend'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'usage', 'title' => __('commerce::coupons.columns.usage'), 'searchable' => false],
                    ['data' => 'dates', 'title' => __('commerce::coupons.columns.dates'), 'searchable' => false],
                    ['data' => 'status', 'title' => __('commerce::coupons.columns.status'), 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[4, 'desc']]"
                :empty-title="__('commerce::coupons.empty_title')"
                :empty-description="__('commerce::coupons.empty_description')"
            >
                <x-slot:filters>
                    <x-ui::filters-bar target="coupons" :search-placeholder="__('commerce::coupons.search')">
                        @if ($active !== 'all')
                            <input type="hidden" name="country_id" value="{{ $active }}" data-filter>
                        @endif
                        <x-ui::select name="status" :label="__('commerce::coupons.filters.status')" label-hidden :options="$statuses" :placeholder="__('commerce::coupons.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::coupons.filters.status')" class="w-40" />
                        <x-ui::select name="type" :label="__('commerce::coupons.filters.type')" label-hidden :options="$types" :placeholder="__('commerce::coupons.filters.type')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::coupons.filters.type')" class="w-44" />
                    </x-ui::filters-bar>
                </x-slot:filters>
                @if ($canManage)
                    <x-slot:empty>
                        <x-ui::button icon="plus" data-remote-modal="coupon-modal" :data-url="route('admin.promotions.coupons.create')">{{ __('commerce::coupons.add') }}</x-ui::button>
                    </x-slot:empty>
                @endif
            </x-ui::datatable>
        </div>

        @if ($canManage)
            <x-ui::modal id="coupon-modal" :title="__('commerce::coupons.create_title')">
                @include('commerce::admin.partials.remote-states')
                <x-slot:footer>
                    <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" form="coupon-form" icon="check" data-remote-submit>{{ __('commerce::promotions.save') }}</x-ui::button>
                </x-slot:footer>
            </x-ui::modal>
        @endif

        <x-ui::modal id="coupon-view-modal" :title="__('commerce::coupons.title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.close') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    </div>
</x-layouts.panel>
