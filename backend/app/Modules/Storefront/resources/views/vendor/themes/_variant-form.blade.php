{{--
    VEN-030b "Home variant — <country>" (Figma Vendor Panel 85:110108), loaded into
    #vendor-theme-variant-modal: radio list of the home variants of the scope's
    current theme family.
--}}
<form
    id="vendor-theme-variant-form"
    method="POST"
    action="{{ vendor_route('store.themes.variant.update', ['scope' => $row->scope()]) }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::vendor_themes.save') }}"
>
    @csrf
    @method('PUT')
    <input type="hidden" name="return" value="{{ $return }}">
    <x-ui::radio-group name="variant" :label="__('content::vendor_themes.variant')" :options="$options" :value="$row->theme->variant" :inline="false" required />
</form>
