{{--
    Admin › Localization › Language create / edit (ADM-091a, Figma 79:90884 — modal
    fields laid out as a page). The code is fixed after creation; Arabic and English
    stay active and free (the switch and price are locked).
--}}
@php
    /** @var \App\Modules\Localization\Models\Language|null $language */
    $editing = $language !== null;
    $core = $editing && $language->isCore();
    $title = $editing
        ? __('localization::admin.languages.edit_title', ['name' => $language->name])
        : __('localization::admin.languages.create_title');
@endphp
<x-layouts.panel surface="admin" :title="$title">
    <x-ui::page-header
        :title="$title"
        :subtitle="$core ? __('localization::admin.languages.locked') : __('localization::admin.languages.form_subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.languages.title') => route('admin.localization.languages.index'), $title => null]"
    />

    <form method="POST" action="{{ $editing ? route('admin.localization.languages.update', $language) : route('admin.localization.languages.store') }}" data-ajax novalidate class="grid gap-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-ui::card :title="__('localization::admin.languages.section_identity')">
            <div class="grid gap-5 md:grid-cols-2">
                @if ($editing)
                    <x-ui::input name="code_display" :label="__('localization::admin.languages.fields.code')" :value="$language->code" disabled dir="ltr" :helper="__('localization::admin.languages.code_fixed')" />
                @else
                    <x-ui::input name="code" :label="__('localization::admin.languages.fields.code')" :helper="__('localization::admin.languages.code_helper')" required dir="ltr" maxlength="12" />
                @endif
                <x-ui::input name="native_name" :label="__('localization::admin.languages.fields.native_name')" :value="$language?->native_name" required maxlength="100" />
                <x-ui::input name="name[en]" :label="__('localization::admin.fields.name_en')" :value="$language?->getTranslation('name', 'en', false)" required dir="ltr" maxlength="100" />
                <x-ui::input name="name[ar]" :label="__('localization::admin.fields.name_ar')" :value="$language?->getTranslation('name', 'ar', false)" required dir="rtl" maxlength="100" />
                <x-ui::select name="direction" :label="__('localization::admin.languages.fields.direction')" :options="\App\Modules\Localization\Enums\TextDirection::options()" :selected="$language?->direction->value ?? 'ltr'" :search="false" required />
                <x-ui::input name="flag" :label="__('localization::admin.languages.fields.flag')" :value="$language?->flag" :helper="__('localization::admin.languages.flag_helper')" maxlength="2" dir="ltr" />
                <x-ui::input name="sort_order" type="number" :label="__('localization::admin.fields.sort_order')" :value="$language?->sort_order ?? 100" required min="0" max="65535" dir="ltr" />
            </div>
        </x-ui::card>

        <x-ui::card :title="__('localization::admin.languages.section_pricing')">
            <div class="grid gap-5 md:grid-cols-2">
                @if ($core)
                    <x-ui::input name="price_display" :label="__('localization::admin.languages.fields.price')" :value="__('localization::admin.languages.free')" disabled :helper="__('localization::admin.languages.locked_hint')" />
                @else
                    <x-ui::input name="price" type="number" step="0.01" min="0.01" :label="__('localization::admin.languages.fields.price')" :value="$editing && $language->price_minor > 0 ? \App\Shared\Money\Money::ofMinor($language->price_minor)->toDecimal() : null" :helper="__('localization::admin.languages.price_helper')" required dir="ltr" />
                @endif
                <x-ui::select name="country_ids" :label="__('localization::admin.languages.fields.countries')" :options="$countries" :selected="$language?->country_ids ?? []" multiple :placeholder="__('localization::admin.languages.all_countries')" :helper="__('localization::admin.languages.countries_helper')" :class="$core ? 'md:col-span-2' : ''" />
            </div>
            <div class="mt-5">
                @if ($core)
                    <input type="hidden" name="is_active" value="1">
                    <x-ui::switch name="is_active_locked" :label="__('localization::admin.fields.active')" :description="__('localization::admin.languages.locked_hint')" checked disabled :unchecked-value="null" />
                @else
                    <x-ui::switch name="is_active" :label="__('localization::admin.fields.active')" :description="__('localization::admin.languages.active_helper')" :checked="$language?->is_active ?? true" />
                @endif
            </div>
        </x-ui::card>

        <div class="flex flex-wrap justify-end gap-3">
            <x-ui::button variant="secondary" :href="route('admin.localization.languages.index')">{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" icon="check">{{ __('localization::admin.save') }}</x-ui::button>
        </div>
    </form>
</x-layouts.panel>
