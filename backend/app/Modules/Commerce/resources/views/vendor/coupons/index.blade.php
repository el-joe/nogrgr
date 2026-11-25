{{--
    Vendor › Ads & Marketing › Coupons (VEN-040, Figma Tenant Panel 87:107268): "My
    coupons" / "Nogrgr coupons (read-only)" tabs with counts, server-side table (code +
    reach, discount, min spend, usage, ends, status), code search + status / country
    filters, view / edit in the coupon modal (VEN-040a) and delete; central rows are
    read-only with hide / show in this store.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('coupons.manage');
    $central = $tab === 'central';
    $tabItems = [
        'own' => ['label' => __('commerce::vendor_promotions.tabs.own_coupons'), 'href' => vendor_route('marketing.coupons.index'), 'count' => number_format($counts['own'])],
        'central' => ['label' => __('commerce::vendor_promotions.tabs.central_coupons'), 'href' => vendor_route('marketing.coupons.index', ['tab' => 'central']), 'count' => number_format($counts['central'])],
    ];
@endphp
<x-vendor::layout :title="__('commerce::coupons.title')" page="vendor-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('commerce::coupons.title')"
            :subtitle="__('commerce::vendor_promotions.coupons.subtitle')"
            :breadcrumbs="[__('commerce::vendor_promotions.section') => null, __('commerce::coupons.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="vendor-coupon-modal" :data-url="vendor_route('marketing.coupons.create')">{{ __('commerce::coupons.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="vendor-coupon-tabs" :active="$tab" :label="__('commerce::coupons.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-coupons"
            :url="vendor_route('marketing.coupons.data')"
            :columns="[
                ['data' => 'code', 'title' => __('commerce::coupons.columns.code'), 'searchable' => false],
                ['data' => 'discount', 'title' => __('commerce::coupons.columns.discount'), 'searchable' => false],
                ['data' => 'min_spend', 'title' => __('commerce::coupons.columns.min_spend'), 'align' => 'end', 'searchable' => false],
                ['data' => 'usage', 'title' => __('commerce::coupons.columns.usage'), 'searchable' => false],
                ['data' => 'ends', 'title' => __('commerce::vendor_promotions.coupons.columns.ends'), 'searchable' => false],
                ['data' => 'status', 'title' => __('commerce::coupons.columns.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('commerce::vendor_promotions.coupons.empty_'.$tab.'_title')"
            :empty-description="__('commerce::vendor_promotions.coupons.empty_'.$tab.'_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-coupons" :search-placeholder="__('commerce::coupons.search')">
                    <input type="hidden" name="tab" value="{{ $tab }}" data-filter>
                    <x-ui::select name="status" :label="__('commerce::vendor_promotions.filters.status')" label-hidden :options="$statuses" :placeholder="__('commerce::vendor_promotions.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('commerce::vendor_promotions.filters.status')" class="w-40" />
                    <x-ui::select name="country_id" :label="__('commerce::vendor_promotions.filters.country')" label-hidden :options="$countries" :placeholder="__('commerce::vendor_promotions.filters.country')" allow-clear data-filter :data-filter-label="__('commerce::vendor_promotions.filters.country')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage && ! $central)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="vendor-coupon-modal" :data-url="vendor_route('marketing.coupons.create')">{{ __('commerce::coupons.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>

        @if ($central)
            <p class="flex items-start gap-2 rounded-card bg-info-bg px-4 py-3 text-[13px] text-info">
                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                <span>{{ __('commerce::vendor_promotions.coupons.central_note') }}</span>
            </p>
        @endif
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-coupon-modal" :title="__('commerce::coupons.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-coupon-form" data-remote-submit>{{ __('commerce::promotions.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

    <x-ui::modal id="vendor-coupon-view-modal" :title="__('commerce::coupons.title')">
        @include('commerce::admin.partials.remote-states')
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-vendor::layout>
