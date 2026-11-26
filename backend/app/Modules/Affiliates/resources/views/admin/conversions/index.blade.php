{{--
    Admin › Affiliates › Conversions (ADM-061, Figma Admin Panel 74:67899): activity tabs,
    "Conversions (first paid subscription only)" with status tabs, search, source filter,
    row view / reverse (approved, paid) / reject (pending).
--}}
@php
    $statusItems = ['all' => ['label' => __('affiliates::admin.conversions.tabs.all'), 'href' => route('admin.affiliates.conversions.index'), 'count' => number_format($counts['all'])]];
    foreach (\App\Modules\Affiliates\Enums\ConversionStatus::cases() as $status) {
        $statusItems[$status->value] = ['label' => $status->label(), 'href' => route('admin.affiliates.conversions.index', ['status' => $status->value]), 'count' => number_format($counts[$status->value])];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('affiliates::admin.conversions.title')" data-page="admin-affiliates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::admin.activity.title')" :subtitle="__('affiliates::admin.conversions.subtitle')"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('navigation.admin.affiliates') => null, __('navigation.admin.conversions') => null]">
            <x-slot:tabs>
                @include('affiliates::admin.partials.activity-tabs', ['active' => 'conversions'])
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::tabs id="conversion-status" variant="segmented" :active="$active" :label="__('affiliates::admin.conversions.title')" :items="$statusItems" />

        <x-ui::datatable
            id="conversions"
            :url="route('admin.affiliates.conversions.data')"
            :columns="[
                ['data' => 'conversion', 'title' => __('affiliates::admin.conversions.columns.conversion')],
                ['data' => 'affiliate', 'title' => __('affiliates::admin.conversions.columns.affiliate'), 'orderable' => false],
                ['data' => 'store', 'title' => __('affiliates::admin.conversions.columns.store'), 'orderable' => false],
                ['data' => 'commission', 'title' => __('affiliates::admin.conversions.columns.commission'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('affiliates::admin.conversions.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('affiliates::admin.conversions.empty_title')"
            :empty-description="__('affiliates::admin.conversions.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="conversions" :search-placeholder="__('affiliates::admin.conversions.search')">
                    @if ($active !== 'all')
                        <input type="hidden" name="status" value="{{ $active }}" data-filter>
                    @endif
                    <x-ui::select name="source" :label="__('affiliates::admin.conversions.source')" label-hidden :options="$sources" :placeholder="__('affiliates::admin.conversions.source')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::admin.conversions.source')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="affiliate-modal" :title="__('affiliates::admin.conversions.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
