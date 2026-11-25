{{--
    Admin › Localization › Country edit (ADM-096): names, phone code, default currency,
    region, active. ISO codes and the flag are reference data and stay as seeded.
--}}
@php
    /** @var \App\Modules\Localization\Models\Country $country */
    $title = __('localization::admin.countries.edit_title', ['name' => $country->name]);
@endphp
<x-layouts.panel surface="admin" :title="$title">
    <x-ui::page-header
        :title="$title"
        :subtitle="__('localization::admin.countries.form_subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.countries.title') => route('admin.localization.countries.index'), $title => null]"
    />

    <form method="POST" action="{{ route('admin.localization.countries.update', $country) }}" data-ajax novalidate class="grid gap-6">
        @csrf
        @method('PUT')

        <x-ui::card>
            <div class="mb-5 flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-full bg-subtle text-2xl" aria-hidden="true">{{ $country->flag }}</span>
                <span class="text-sm text-ink-secondary"><bdi dir="ltr">{{ $country->iso2 }} · {{ $country->iso3 }}@if ($country->numeric_code) · {{ $country->numeric_code }}@endif</bdi></span>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <x-ui::input name="name[en]" :label="__('localization::admin.fields.name_en')" :value="$country->getTranslation('name', 'en', false)" required dir="ltr" maxlength="100" />
                <x-ui::input name="name[ar]" :label="__('localization::admin.fields.name_ar')" :value="$country->getTranslation('name', 'ar', false)" required dir="rtl" maxlength="100" />
                <x-ui::input name="phone_code" :label="__('localization::admin.countries.fields.phone_code')" :value="$country->phone_code" :helper="__('localization::admin.countries.phone_helper')" dir="ltr" maxlength="10" />
                <x-ui::select name="currency_code" :label="__('localization::admin.countries.fields.currency')" :options="$currencies" :selected="$country->currency_code" :placeholder="__('localization::admin.countries.no_currency')" allow-clear />
                <x-ui::select name="continent" :label="__('localization::admin.countries.fields.region')" :options="\App\Modules\Localization\Enums\Continent::options()" :selected="$country->continent?->value" :placeholder="__('localization::admin.countries.no_region')" :search="false" allow-clear />
            </div>
            <div class="mt-5">
                <x-ui::switch name="is_active" :label="__('localization::admin.fields.active')" :description="__('localization::admin.countries.active_helper')" :checked="$country->is_active" />
            </div>
        </x-ui::card>

        <div class="flex flex-wrap justify-end gap-3">
            <x-ui::button variant="secondary" :href="route('admin.localization.countries.index')">{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" icon="check">{{ __('localization::admin.save') }}</x-ui::button>
        </div>
    </form>
</x-layouts.panel>
