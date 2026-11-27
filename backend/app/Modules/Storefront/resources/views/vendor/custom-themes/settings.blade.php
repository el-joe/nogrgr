{{--
    Vendor › Custom theme › Theme settings (VEN-036b): a form generated from the live (else
    newest valid) version's theme.json `settings` schema — colour, text, long text, number,
    choice, on / off and font fields, labels / help in the panel language. Values are
    checked against the schema on save (CustomThemeService::saveSettings).
--}}
@php
    use App\Modules\Storefront\Enums\CustomThemeSettingType;
    use App\Modules\Storefront\Enums\StoreFont;

    $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
    $fonts = [];
    foreach (StoreFont::cases() as $font) {
        $fonts[$font->value] = $font->family();
    }
@endphp
<x-vendor::layout :title="__('content::custom_themes.settings.title')" page="vendor-custom-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="__('content::custom_themes.settings.title')"
            :subtitle="__('content::custom_themes.settings.subtitle')"
            :breadcrumbs="[__('content::custom_themes.crumb') => null, __('content::custom_themes.title') => vendor_route('store.custom-themes.index'), __('content::custom_themes.settings.title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" :href="vendor_route('store.custom-themes.index')">{{ __('content::custom_themes.actions.back') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::card :title="$version ? __('content::custom_themes.settings.version_note', ['name' => $version->name, 'version' => $version->version]) : null">
            @if (! $version)
                <x-ui::empty-state icon="palette" :title="__('content::custom_themes.settings.no_theme_title')" :description="__('content::custom_themes.settings.no_theme_description')">
                    <x-ui::button variant="secondary" :href="vendor_route('store.custom-themes.index')">{{ __('content::custom_themes.title') }}</x-ui::button>
                </x-ui::empty-state>
            @elseif ($fields === [])
                <x-ui::empty-state icon="settings" :title="__('content::custom_themes.settings.empty_title')" :description="__('content::custom_themes.settings.empty_description')" />
            @else
                <form method="POST" action="{{ vendor_route('store.custom-themes.settings.update') }}" data-ajax novalidate class="grid gap-5" id="custom-theme-settings-form">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($fields as $field)
                            @php
                                $name = 'settings['.$field['id'].']';
                                $label = $field['label'][$locale] ?? $field['id'];
                                $help = $field['help'][$locale] ?? null;
                                $value = $values[$field['id']] ?? $field['default'];
                                $type = CustomThemeSettingType::tryFrom($field['type']);
                            @endphp
                            @switch($type)
                                @case(CustomThemeSettingType::Color)
                                    <x-ui::input :name="$name" type="color" :label="$label" :value="$value" :helper="$help" class="h-11" data-setting="{{ $field['id'] }}" />
                                    @break
                                @case(CustomThemeSettingType::Textarea)
                                    <x-ui::textarea :name="$name" :label="$label" :value="$value" :helper="$help" :maxlength="$field['max_length'] ?? 2000" :rows="3" class="md:col-span-2" data-setting="{{ $field['id'] }}" />
                                    @break
                                @case(CustomThemeSettingType::Number)
                                    <x-ui::input :name="$name" type="number" :label="$label" :value="$value" :helper="$help" :min="$field['min'] ?? null" :max="$field['max'] ?? null" step="1" data-setting="{{ $field['id'] }}" />
                                    @break
                                @case(CustomThemeSettingType::Select)
                                    <x-ui::select :name="$name" :label="$label" :options="collect($field['options'])->mapWithKeys(fn ($option) => [$option['value'] => $option['label'][$locale] ?? $option['value']])->all()" :selected="$value" :helper="$help" :search="false" data-setting="{{ $field['id'] }}" />
                                    @break
                                @case(CustomThemeSettingType::Font)
                                    <x-ui::select :name="$name" :label="$label" :options="$fonts" :selected="$value" :helper="$help" :search="false" data-setting="{{ $field['id'] }}" />
                                    @break
                                @case(CustomThemeSettingType::Toggle)
                                    <div class="flex items-center md:col-span-2">
                                        <x-ui::switch :name="$name" :label="$label" :description="$help" :checked="(bool) $value" data-setting="{{ $field['id'] }}" />
                                    </div>
                                    @break
                                @default
                                    <x-ui::input :name="$name" :label="$label" :value="$value" :helper="$help" :maxlength="$field['max_length'] ?? 500" data-setting="{{ $field['id'] }}" />
                            @endswitch
                        @endforeach
                    </div>
                    @if ($canManage)
                        <div><x-ui::button type="submit" icon="check">{{ __('content::custom_themes.settings.save') }}</x-ui::button></div>
                    @endif
                </form>
            @endif
        </x-ui::card>
    </div>
</x-vendor::layout>
