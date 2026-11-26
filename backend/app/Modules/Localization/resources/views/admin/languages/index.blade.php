{{--
    Admin › Localization › Languages (ADM-091, Figma 79:81928): Active / All ISO tabs,
    server-side table with search + direction / status filters, active switch,
    edit and delete. Arabic and English are locked (always active and free).
    ADM-091b: row action "Translate catalogue with AI" (active languages) starts an admin
    AI run; its progress modal (live, retry / stop) and the latest-run banner come from
    the Ai module (resources/js/panels/admin/ai-translation-runs.js).
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('localization.languages.manage');
    $tableUrl = route('admin.localization.languages.data', $tab === 'all' ? [] : ['status' => 'active']);
@endphp
<x-layouts.panel surface="admin" :title="__('localization::admin.languages.title')">
    <div data-page="admin-languages" class="grid gap-6">
    <x-ui::page-header
        :title="__('localization::admin.languages.title')"
        :subtitle="__('localization::admin.languages.subtitle', ['count' => $counts['all']])"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.languages.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.localization.languages.create')">{{ __('localization::admin.languages.add') }}</x-ui::button>
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            <x-ui::tabs id="languages-tabs" :active="$tab" :label="__('localization::admin.languages.title')" :items="array_filter([
                'active' => ['label' => __('localization::admin.tabs.active'), 'count' => $counts['active'], 'href' => route('admin.localization.languages.index')],
                'all' => ['label' => __('localization::admin.languages.tab_all'), 'count' => $counts['all'], 'href' => route('admin.localization.languages.index', ['tab' => 'all'])],
                'purchases' => auth('admin')->user()?->can('localization.purchases.view') ? ['label' => __('localization::purchases.tab'), 'href' => route('admin.localization.purchases.index')] : null,
            ])" />
        </x-slot:tabs>
    </x-ui::page-header>

    @include('ai::admin.translation.banner', ['kind' => \App\Modules\Ai\Enums\AdminTranslationKind::Catalog])

    <x-ui::datatable
        id="languages"
        :url="$tableUrl"
        :columns="[
            ['data' => 'name', 'title' => __('localization::admin.languages.columns.language')],
            ['data' => 'direction', 'title' => __('localization::admin.languages.columns.direction')],
            ['data' => 'price', 'title' => __('localization::admin.languages.columns.price'), 'align' => 'end'],
            ['data' => 'countries', 'title' => __('localization::admin.languages.columns.countries')],
            ['data' => 'sort_order', 'title' => __('localization::admin.columns.sort'), 'align' => 'end'],
            ['data' => 'is_active', 'title' => __('localization::admin.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[4, 'asc']]"
        :empty-title="__('localization::admin.languages.empty_title')"
        :empty-description="__('localization::admin.languages.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="languages" :search-placeholder="__('localization::admin.languages.search')">
                <x-ui::select name="direction" :label="__('localization::admin.languages.columns.direction')" label-hidden :options="\App\Modules\Localization\Enums\TextDirection::options()" :placeholder="__('localization::admin.languages.columns.direction')" :search="false" allow-clear data-filter :data-filter-label="__('localization::admin.languages.columns.direction')" class="w-44" />
                @if ($tab === 'all')
                    <x-ui::select name="status" :label="__('localization::admin.columns.status')" label-hidden :options="\App\Modules\Localization\Enums\ActiveState::options()" :placeholder="__('localization::admin.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('localization::admin.columns.status')" class="w-40" />
                @endif
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.localization.languages.create')">{{ __('localization::admin.languages.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
    </div>

    @include('ai::admin.translation.progress-modal')
</x-layouts.panel>
