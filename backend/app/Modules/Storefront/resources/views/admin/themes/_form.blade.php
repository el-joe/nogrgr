{{-- Theme edit (ADM-086a, Figma Admin Panel 78:77708): name + description per language, preview image (upload / remove). --}}
<form
    id="theme-form"
    method="POST"
    action="{{ route('admin.storefront.themes.update', $theme) }}"
    enctype="multipart/form-data"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::admin_themes.save') }}"
>
    @csrf
    @method('PUT')
    <x-ui::locale-tabs id="theme-i18n" :locales="$locales">
        @foreach ($locales as $code => $language)
            <x-ui::locale-pane parent="theme-i18n" :locale="$code">
                <div class="grid gap-5">
                    <x-ui::input :name="'name['.$code.']'" :label="__('content::admin_themes.fields.name')" :value="$theme->getTranslation('name', $code, false)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="80" :dir="$language['dir'] ?? 'ltr'" />
                    <x-ui::textarea :name="'description['.$code.']'" :label="__('content::admin_themes.fields.description')" :value="$theme->getTranslation('description', $code, false)" maxlength="500" rows="3" :dir="$language['dir'] ?? 'ltr'" />
                </div>
            </x-ui::locale-pane>
        @endforeach
    </x-ui::locale-tabs>
    <x-ui::image-upload name="preview[]" :label="__('content::admin_themes.fields.preview')" :images="$theme->preview_path ? [['id' => 'preview', 'url' => $theme->preview_url, 'alt' => (string) $theme->name]] : []" :max="1" :main="false" accept="image/png,image/jpeg,image/webp,image/avif" :helper="__('content::admin_themes.fields.preview_help')" />
</form>
