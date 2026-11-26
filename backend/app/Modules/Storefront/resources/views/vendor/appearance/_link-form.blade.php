{{-- Header / footer link create / edit (VEN-034d/e), loaded into #appearance-modal: label per language, link, footer column, visible. --}}
@php($isFooter = $link->placement === \App\Modules\Storefront\Enums\MenuLinkPlacement::Footer)
<form
    id="appearance-link-form"
    method="POST"
    action="{{ $link->exists ? vendor_route('store.appearance.links.update', ['link' => $link->id]) : vendor_route('store.appearance.links.store') }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::store_appearance.save') }}"
>
    @csrf
    @if ($link->exists)
        @method('PUT')
    @endif
    <input type="hidden" name="placement" value="{{ $link->placement->value }}">
    <x-ui::locale-tabs id="appearance-link-i18n" :locales="$locales">
        @foreach ($locales as $code => $language)
            <x-ui::locale-pane parent="appearance-link-i18n" :locale="$code" :active="array_key_first($locales)">
                <x-ui::input :name="'label['.$code.']'" :label="__('content::store_appearance.links.label')" :value="$link->exists ? $link->getTranslation('label', $code, false) : null" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="60" :dir="$language['dir'] ?? 'ltr'" />
            </x-ui::locale-pane>
        @endforeach
    </x-ui::locale-tabs>
    <x-ui::input name="url" :label="__('content::store_appearance.links.url')" :value="$link->url" maxlength="500" dir="ltr" placeholder="/track" :helper="__('content::store_appearance.links.url_help')" required />
    @if ($isFooter)
        <x-ui::select name="footer_column" :label="__('content::store_appearance.links.column')" :options="$columns" :selected="$link->footer_column?->value" :search="false" required />
    @endif
    <x-ui::switch name="is_active" :label="__('content::store_appearance.links.active')" :checked="(bool) $link->is_active" />
</form>
