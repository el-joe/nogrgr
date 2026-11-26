{{--
    Vendor › Settings › Store translations (VEN-075, Vendor Panel (new screens) 90:114518):
    storefront static texts of one language — tabs All keys / Missing {language} / Locked
    (by Nogrgr) / Edited, search, language picker, key · English · language · lock columns,
    edit (with "Translate" suggestion), reset; "Translate missing with AI" (queued).
--}}
@php
    $language = $locales[$locale] ?? strtoupper($locale);
    $tabItems = [
        'all' => ['label' => __('localization::vendor_translations.tabs.all'), 'href' => vendor_route('settings.translations', ['locale' => $locale]), 'count' => number_format($counts['all'])],
        'missing' => ['label' => __('localization::vendor_translations.tabs.missing', ['language' => strtoupper($locale)]), 'href' => vendor_route('settings.translations', ['locale' => $locale, 'tab' => 'missing']), 'count' => number_format($counts['missing'])],
        'locked' => ['label' => __('localization::vendor_translations.tabs.locked'), 'href' => vendor_route('settings.translations', ['locale' => $locale, 'tab' => 'locked']), 'count' => number_format($counts['locked'])],
        'edited' => ['label' => __('localization::vendor_translations.tabs.edited'), 'href' => vendor_route('settings.translations', ['locale' => $locale, 'tab' => 'edited'])],
    ];
@endphp
<x-vendor::layout :title="__('localization::vendor_translations.title')" page="vendor-translations">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('localization::vendor_translations.title')" :subtitle="__('localization::vendor_translations.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('localization::vendor_translations.title') => null]">
            @if ($canManage && $locale !== 'en' && $counts['missing'] > 0)
                <x-slot:actions>
                    <x-ui::button icon="sparkles" :href="vendor_route('settings.translations.static.translate-missing', ['locale' => $locale])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('localization::vendor_translations.translate_missing')" :data-confirm="__('localization::vendor_translations.translate_missing_confirm', ['count' => $counts['missing'], 'language' => $language])">{{ __('localization::vendor_translations.translate_missing') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="vendor-translation-tabs" :active="$tab" :label="__('localization::vendor_translations.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @include('localization::vendor.translations._layout-top')

        <x-ui::datatable
            id="vendor-static-texts"
            :url="vendor_route('settings.translations.data', ['locale' => $locale, 'tab' => $tab])"
            row-key="key"
            :columns="[
                ['data' => 'key', 'title' => __('localization::vendor_translations.columns.key'), 'orderable' => false],
                ['data' => 'english', 'title' => __('localization::vendor_translations.columns.english'), 'orderable' => false],
                ['data' => 'value', 'title' => $language, 'orderable' => false],
                ['data' => 'lock', 'title' => __('localization::vendor_translations.columns.lock'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :empty-title="__('localization::vendor_translations.empty_'.$tab.'_title')"
            :empty-description="__('localization::vendor_translations.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-static-texts" :search-placeholder="__('localization::vendor_translations.search')" />
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    @include('localization::vendor.translations._edit-modal')
</x-vendor::layout>
