{{--
    New product request ("Order new Product", Figma 12:14977) and new brand request
    ("Request a private brand", Vendor Panel (new screens) 87:111915). Modal fragment
    (remote modal) and the body of the full page when opened by URL. Files go with the
    form and become the opening message of the conversation.
    $kind, $countries (id => name), $categories (id => name).
--}}
@php
    $isBrand = $kind === \App\Modules\Catalog\Enums\RequestKind::Brand;
    $key = $kind->value;
@endphp
<form id="vendor-request-form" method="POST" action="{{ vendor_route('requests.'.$key.'.store') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax novalidate
    data-modal-title="{{ __('catalog::vendor_requests.new.'.$key) }}"
    data-modal-subtitle="{{ $isBrand ? __('catalog::vendor_requests.create.brand_subtitle') : __('catalog::vendor_requests.create.product_subtitle') }}"
    data-submit-label="{{ __('catalog::vendor_requests.create.submit') }}">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::input name="subject" :label="__('catalog::vendor_requests.fields.subject_'.$key)" :placeholder="__('catalog::vendor_requests.fields.subject_'.$key.'_placeholder')" maxlength="255" required />
        @if ($isBrand)
            <x-ui::select name="category_id" :label="__('catalog::vendor_requests.fields.category')" :options="$categories" :placeholder="__('catalog::vendor_requests.fields.category_placeholder')" allow-clear />
        @else
            <x-ui::input name="url" type="url" :label="__('catalog::vendor_requests.fields.url')" placeholder="https://" maxlength="2000" dir="ltr" />
        @endif
    </div>

    <x-ui::select name="country_ids" multiple :label="$isBrand ? __('catalog::vendor_requests.fields.countries') : __('catalog::vendor_requests.fields.target_markets')" :options="$countries" :placeholder="__('catalog::vendor_requests.fields.countries_placeholder')" />

    @if ($isBrand)
        <x-ui::input name="url" type="url" :label="__('catalog::vendor_requests.fields.website')" placeholder="https://" maxlength="2000" dir="ltr" />
    @endif

    <x-ui::textarea name="notes" :label="$isBrand ? __('catalog::vendor_requests.fields.notes_brand') : __('catalog::vendor_requests.fields.notes')" :placeholder="__('catalog::vendor_requests.fields.notes_placeholder')" maxlength="5000" rows="5" />

    <x-ui::file-upload name="files" multiple :label="__('catalog::vendor_requests.fields.files')" :hint="__('catalog::vendor_requests.fields.files_hint')" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" />
</form>
