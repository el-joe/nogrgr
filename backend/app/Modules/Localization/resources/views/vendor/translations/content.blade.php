{{--
    Vendor › Settings › Store translations › Store content (VEN-075): one translatable
    field of the store's own categories / products / flash sales per language. Saving a
    value locks it so the paid AI translation never overwrites it; unlock hands it back.
--}}
@php $language = $locales[$locale] ?? strtoupper($locale); @endphp
<x-vendor::layout :title="__('localization::vendor_translations.sections.content')" page="vendor-translations">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('localization::vendor_translations.title')" :subtitle="__('localization::vendor_translations.content_subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('localization::vendor_translations.title') => vendor_route('settings.translations'), __('localization::vendor_translations.sections.content') => null]" />

        @include('localization::vendor.translations._layout-top')

        <x-ui::datatable
            id="vendor-content-translations"
            :url="vendor_route('settings.translations.content.data', ['locale' => $locale, 'type' => $type->value, 'field' => $field])"
            :columns="[
                ['data' => 'record', 'title' => $type->label(), 'orderable' => true],
                ['data' => 'english', 'title' => __('localization::vendor_translations.columns.english'), 'orderable' => false],
                ['data' => 'value', 'title' => $language, 'orderable' => false],
                ['data' => 'lock', 'title' => __('localization::vendor_translations.columns.lock'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('localization::vendor_translations.content_empty_title')"
            :empty-description="__('localization::vendor_translations.content_empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-content-translations" :search-placeholder="__('localization::vendor_translations.content_search')" :chips="false">
                    <form method="GET" action="{{ vendor_route('settings.translations.content') }}" class="flex flex-wrap items-center gap-2" data-translation-locale>
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <x-ui::select name="type" :label="__('localization::vendor_translations.fields.type')" label-hidden :options="$types" :selected="$type->value" :search="false" class="w-44" data-auto-submit />
                        <x-ui::select name="field" :label="__('localization::vendor_translations.fields.field')" label-hidden :options="$fields" :selected="$field" :search="false" class="w-44" data-auto-submit />
                    </form>
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    @include('localization::vendor.translations._edit-modal')
</x-vendor::layout>
