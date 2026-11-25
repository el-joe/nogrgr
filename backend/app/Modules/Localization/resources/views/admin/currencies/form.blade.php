{{--
    Admin › Localization › Currency create / edit (ADM-095a, Figma 79:90884 "Edit currency").
    The code is fixed after creation; USD (base) keeps rate 1 and stays active.
--}}
@php
    /** @var \App\Modules\Localization\Models\Currency|null $currency */
    $editing = $currency !== null;
    $base = $editing && $currency->isBase();
    $title = $editing
        ? __('localization::admin.currencies.edit_title', ['code' => $currency->code])
        : __('localization::admin.currencies.create_title');
@endphp
<x-layouts.panel surface="admin" :title="$title">
    <x-ui::page-header
        :title="$title"
        :subtitle="$base ? __('localization::admin.currencies.locked') : __('localization::admin.currencies.form_subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.currencies.title') => route('admin.localization.currencies.index'), $title => null]"
    />

    <form method="POST" action="{{ $editing ? route('admin.localization.currencies.update', $currency) : route('admin.localization.currencies.store') }}" data-ajax novalidate class="grid gap-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-ui::card>
            <div class="grid gap-5 md:grid-cols-2">
                <x-ui::input name="name[en]" :label="__('localization::admin.fields.name_en')" :value="$currency?->getTranslation('name', 'en', false)" required dir="ltr" maxlength="100" />
                <x-ui::input name="name[ar]" :label="__('localization::admin.fields.name_ar')" :value="$currency?->getTranslation('name', 'ar', false)" required dir="rtl" maxlength="100" />
                @if ($editing)
                    <x-ui::input name="code_display" :label="__('localization::admin.currencies.fields.code')" :value="$currency->code" disabled dir="ltr" :helper="__('localization::admin.currencies.code_fixed')" />
                @else
                    <x-ui::input name="code" :label="__('localization::admin.currencies.fields.code')" :helper="__('localization::admin.currencies.code_helper')" required dir="ltr" maxlength="3" />
                @endif
                <x-ui::input name="symbol" :label="__('localization::admin.currencies.fields.symbol')" :value="$currency?->symbol" required maxlength="16" />
                <x-ui::input name="decimals" type="number" :label="__('localization::admin.currencies.fields.decimals')" :value="$currency?->decimals ?? 2" required min="0" max="4" dir="ltr" />
                @if ($base)
                    <x-ui::input name="rate_display" :label="__('localization::admin.currencies.fields.rate')" value="1.000000" disabled dir="ltr" :helper="__('localization::admin.currencies.base_rate_helper')" />
                @else
                    <x-ui::input name="rate" type="number" step="0.000001" min="0" :label="__('localization::admin.currencies.fields.rate')" :value="$currency?->rate" :required="! $editing" dir="ltr" :helper="__('localization::admin.currencies.rate_helper')" />
                @endif
                <x-ui::input name="sort_order" type="number" :label="__('localization::admin.fields.sort_order')" :value="$currency?->sort_order ?? 100" required min="0" max="65535" dir="ltr" />
            </div>
            <div class="mt-5">
                @if ($base)
                    <input type="hidden" name="is_active" value="1">
                    <x-ui::switch name="is_active_locked" :label="__('localization::admin.fields.active')" :description="__('localization::admin.currencies.locked')" checked disabled :unchecked-value="null" />
                @else
                    <x-ui::switch name="is_active" :label="__('localization::admin.fields.active')" :description="__('localization::admin.currencies.active_helper')" :checked="$currency?->is_active ?? true" />
                @endif
            </div>
        </x-ui::card>

        <div class="flex flex-wrap justify-end gap-3">
            <x-ui::button variant="secondary" :href="route('admin.localization.currencies.index')">{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" icon="check">{{ __('localization::admin.save') }}</x-ui::button>
        </div>
    </form>
</x-layouts.panel>
