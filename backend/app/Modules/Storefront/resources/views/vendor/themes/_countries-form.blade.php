{{--
    VEN-030a "Use <theme> in…" (Figma Vendor Panel 85:110108), loaded into
    #vendor-theme-modal: the store's selling countries (multi-select) and
    "Make it the store default".
--}}
<form
    id="vendor-theme-countries-form"
    method="POST"
    action="{{ vendor_route('store.themes.activate') }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::vendor_themes.save') }}"
>
    @csrf
    <input type="hidden" name="theme" value="{{ $theme->slug }}">
    <input type="hidden" name="return" value="{{ $return }}">
    <x-ui::select name="country_ids" multiple :label="__('content::vendor_themes.fields.countries')" :options="$countries" :selected="$selected" :placeholder="__('content::vendor_themes.fields.countries_placeholder')" :helper="__('content::vendor_themes.fields.countries_help')" />
    <x-ui::switch name="default" :label="__('content::vendor_themes.fields.default')" :description="__('content::vendor_themes.fields.default_help')" :checked="$isDefault" />
</form>
