{{--
    Template part create / edit (ADM-088a; old AddEditTemplatePart): theme, part type, name,
    the settings the type declares (ThemePartType::fields — text per language, link,
    colour, switches), preview image, active + default.
--}}
@php
    $currentType = $part->type?->value ?? \App\Modules\Storefront\Enums\ThemePartType::Announcement->value;
    $value = fn (string $field, mixed $default = null) => ($part->settings ?? [])[$field] ?? $default;
@endphp
<form
    id="theme-part-form"
    method="POST"
    action="{{ $part->exists ? route('admin.storefront.template-parts.update', $part) : route('admin.storefront.template-parts.store') }}"
    enctype="multipart/form-data"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::admin_themes.save') }}"
    x-data="{{ json_encode(['type' => $currentType]) }}"
    x-on:change="if ($event.target.name === 'type') type = $event.target.value"
>
    @csrf
    @if ($part->exists)
        @method('PUT')
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="theme_id" :label="__('content::admin_themes.parts.fields.theme')" :options="$themes" :selected="(string) $part->theme_id" required />
        <x-ui::select name="type" :label="__('content::admin_themes.parts.fields.type')" :options="\App\Modules\Storefront\Enums\ThemePartType::options()" :selected="$currentType" :search="false" required />
    </div>
    <x-ui::input name="name" :label="__('content::admin_themes.parts.fields.name')" :value="$part->name" maxlength="120" required />

    <fieldset class="grid gap-4 rounded-control border border-line p-4">
        <legend class="px-1 text-sm font-semibold text-ink">{{ __('content::admin_themes.parts.fields.settings') }}</legend>
        @foreach ($types as $type)
            <div class="grid gap-4" x-show="type === '{{ $type->value }}'" @if ($type->value !== $currentType) x-cloak @endif data-part-fields="{{ $type->value }}">
                <p class="text-xs text-ink-secondary">{{ __('content::admin_themes.parts.type_help.'.$type->value) }}</p>
                @foreach ($type->fields() as $field)
                    @php $current = $type->value === $currentType ? $value($field['name'], $field['default'] ?? null) : ($field['default'] ?? null); @endphp
                    @switch($field['kind'])
                        @case('text')
                            <x-ui::locale-tabs :id="'part-'.$type->value.'-'.$field['name']" :locales="$locales">
                                @foreach ($locales as $code => $language)
                                    <x-ui::locale-pane :parent="'part-'.$type->value.'-'.$field['name']" :locale="$code">
                                        <x-ui::input :name="'settings['.$field['name'].']['.$code.']'" :id="'part-'.$type->value.'-'.$field['name'].'-'.$code" :label="__('content::admin_themes.part_fields.'.$field['name'])" :value="is_array($current) ? ($current[$code] ?? null) : null" :maxlength="$field['max'] ?? 255" :required="($field['required'] ?? false) && in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" :dir="$language['dir'] ?? 'ltr'" />
                                    </x-ui::locale-pane>
                                @endforeach
                            </x-ui::locale-tabs>
                            @break
                        @case('link')
                            <x-ui::input :name="'settings['.$field['name'].']'" :id="'part-'.$type->value.'-'.$field['name']" :label="__('content::admin_themes.part_fields.'.$field['name'])" :value="is_string($current) ? $current : null" :maxlength="$field['max'] ?? 255" dir="ltr" placeholder="/offers" :helper="__('content::admin_themes.part_fields.link_help')" />
                            @break
                        @case('select')
                            <x-ui::select :name="'settings['.$field['name'].']'" :id="'part-'.$type->value.'-'.$field['name']" :label="__('content::admin_themes.part_fields.'.$field['name'])" :options="collect($field['options'])->mapWithKeys(fn ($option) => [$option => __('content::admin_themes.tones.'.$option)])->all()" :selected="(string) $current" :search="false" required />
                            @break
                        @case('toggle')
                            <x-ui::switch :name="'settings['.$field['name'].']'" :id="'part-'.$type->value.'-'.$field['name']" :label="__('content::admin_themes.part_fields.'.$field['name'])" :checked="(bool) $current" />
                            @break
                    @endswitch
                @endforeach
            </div>
        @endforeach
    </fieldset>

    <x-ui::image-upload name="preview[]" :label="__('content::admin_themes.fields.preview')" :images="$part->preview_path ? [['id' => 'preview', 'url' => $part->preview_url, 'alt' => $part->name]] : []" :max="1" :main="false" accept="image/png,image/jpeg,image/webp,image/avif" :helper="__('content::admin_themes.fields.preview_help')" />
    <div class="grid gap-3">
        <x-ui::switch name="is_active" :label="__('content::admin_themes.parts.fields.active')" :checked="(bool) ($part->is_active ?? true)" />
        <x-ui::switch name="is_default" :label="__('content::admin_themes.parts.fields.default')" :description="__('content::admin_themes.parts.fields.default_help')" :checked="(bool) $part->is_default" />
    </div>
</form>
