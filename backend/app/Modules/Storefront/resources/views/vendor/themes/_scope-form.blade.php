{{--
    VEN-030b "Theme — <scope>" (Figma Vendor Panel 85:110108), loaded into
    #vendor-theme-scope-modal: the themes allowed in that scope (store default = every
    listed theme).
--}}
<form
    id="vendor-theme-scope-form"
    method="POST"
    action="{{ vendor_route('store.themes.scope.update', ['scope' => $row->scope()]) }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::vendor_themes.save') }}"
>
    @csrf
    @method('PUT')
    <input type="hidden" name="return" value="{{ $return }}">
    <x-ui::select name="theme" :label="__('content::vendor_themes.fields.theme')" :options="$options" :selected="$row->theme?->id" :placeholder="__('content::vendor_themes.fields.theme_placeholder')" :helper="$row->countryId !== null ? __('content::vendor_themes.fields.theme_help') : null" required />
</form>
