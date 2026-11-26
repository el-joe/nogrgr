{{--
    Admin › Affiliates › Promo codes (ADM-063, Figma Admin Panel 74:67899): activity tabs,
    codes with affiliate, discount, valid until, uses, status; New code / edit modal, delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('affiliates.promo-codes.manage');
    $states = ['active' => __('affiliates::admin.promo.states.active'), 'expired' => __('affiliates::admin.promo.states.expired'), 'inactive' => __('affiliates::admin.promo.states.inactive')];
@endphp
<x-layouts.panel surface="admin" :title="__('affiliates::admin.promo.title')" data-page="admin-affiliates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::admin.activity.title')" :subtitle="__('affiliates::admin.promo.subtitle')"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('navigation.admin.affiliates') => null, __('navigation.admin.promo_codes') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.promo-codes.create')">{{ __('affiliates::admin.promo.create') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                @include('affiliates::admin.partials.activity-tabs', ['active' => 'promo-codes'])
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="promo-codes"
            :url="route('admin.affiliates.promo-codes.data')"
            :columns="[
                ['data' => 'code', 'title' => __('affiliates::admin.promo.columns.code')],
                ['data' => 'discount', 'title' => __('affiliates::admin.promo.columns.discount')],
                ['data' => 'ends_at', 'title' => __('affiliates::admin.promo.columns.ends_at')],
                ['data' => 'uses', 'title' => __('affiliates::admin.promo.columns.uses'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('affiliates::admin.promo.columns.status'), 'orderable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[2, 'desc']]"
            :empty-title="__('affiliates::admin.promo.empty_title')"
            :empty-description="__('affiliates::admin.promo.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="promo-codes" :search-placeholder="__('affiliates::admin.promo.search')">
                    <x-ui::select name="state" :label="__('affiliates::admin.promo.columns.status')" label-hidden :options="$states" :placeholder="__('affiliates::admin.promo.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::admin.promo.columns.status')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="affiliate-modal" :title="__('affiliates::admin.promo.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
