{{--
    Admin › Localization › Countries (ADM-096, Figma 79:85682): every ISO country with
    flag, default currency and phone code. Enabled / All tabs, server-side table with
    search + region / status filters, active switch and edit.
--}}
@php
    $tableUrl = route('admin.localization.countries.data', $tab === 'all' ? [] : ['status' => 'active']);
@endphp
<x-layouts.panel surface="admin" :title="__('localization::admin.countries.title')">
    <x-ui::page-header
        :title="__('localization::admin.countries.title')"
        :subtitle="__('localization::admin.countries.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.countries.title') => null]"
    >
        <x-slot:tabs>
            <x-ui::tabs id="countries-tabs" :active="$tab" :label="__('localization::admin.countries.title')" :items="[
                'active' => ['label' => __('localization::admin.countries.tab_enabled'), 'count' => $counts['active'], 'href' => route('admin.localization.countries.index')],
                'all' => ['label' => __('localization::admin.tabs.all'), 'count' => $counts['all'], 'href' => route('admin.localization.countries.index', ['tab' => 'all'])],
            ]" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="countries"
        :url="$tableUrl"
        :columns="[
            ['data' => 'name', 'title' => __('localization::admin.countries.columns.country')],
            ['data' => 'currency_code', 'title' => __('localization::admin.countries.columns.currency')],
            ['data' => 'phone_code', 'title' => __('localization::admin.countries.columns.phone')],
            ['data' => 'continent', 'title' => __('localization::admin.countries.columns.region')],
            ['data' => 'is_active', 'title' => __('localization::admin.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('localization::admin.countries.empty_title')"
        :empty-description="__('localization::admin.countries.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="countries" :search-placeholder="__('localization::admin.countries.search')">
                <x-ui::select name="continent" :label="__('localization::admin.countries.columns.region')" label-hidden :options="\App\Modules\Localization\Enums\Continent::options()" :placeholder="__('localization::admin.countries.columns.region')" :search="false" allow-clear data-filter :data-filter-label="__('localization::admin.countries.columns.region')" class="w-44" />
                @if ($tab === 'all')
                    <x-ui::select name="status" :label="__('localization::admin.columns.status')" label-hidden :options="\App\Modules\Localization\Enums\ActiveState::options()" :placeholder="__('localization::admin.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('localization::admin.columns.status')" class="w-40" />
                @endif
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
