{{--
    Request a central edit (VEN-013a, Vendor Panel (new screens) 83:104468): field (title /
    descriptions / SEO per language, images, weight), new value with the current value
    shown for the diff, reason and new images. Submitted through EditRequestService::submit().
    Data: $product (StoreProduct|null), $view (StoreProductView|null), $current (field[:locale] => value), $field.
--}}
@php
    $options = [];
    foreach (\App\Modules\Catalog\Enums\EditableProductField::cases() as $case) {
        if ($case->isTranslatable()) {
            foreach (\App\Shared\Support\Locale::enabled() as $code => $language) {
                $options[$case->value.':'.$code] = $case->label().' ('.(__('ui.languages.'.$code) !== 'ui.languages.'.$code ? __('ui.languages.'.$code) : $language['name']).')';
            }
        } else {
            $options[$case->value] = $case->label();
        }
    }
    $selected = $field !== null ? (array_key_exists($field, $options) ? $field : null) : array_key_first($options);
@endphp
<form id="edit-request-form" method="POST" action="{{ vendor_route('catalog.edit-requests.store') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax data-edit-request-form novalidate
    data-modal-title="{{ __('catalog::vendor.edit_requests.modal_title') }}"
    @if ($view) data-modal-subtitle="{{ $view->name }}" @endif>
    @csrf
    @if ($product)
        <input type="hidden" name="product_id" value="{{ $product->id }}">
    @else
        <x-ui::select name="product_id" :label="__('catalog::vendor.edit_requests.product')" :ajax-url="vendor_route('catalog.products.options')" :placeholder="__('catalog::vendor.edit_requests.pick_product')" required />
    @endif

    <x-ui::select name="field_key" :label="__('catalog::vendor.edit_requests.field')" :options="$options" :selected="$selected" :search="false" required data-request-field-select />

    @if ($current !== [])
        <div class="grid gap-2 sm:grid-cols-2" data-request-diff>
            <div class="rounded-control border border-line bg-subtle p-3">
                <p class="text-xs font-medium text-ink-secondary">{{ __('catalog::vendor.edit_requests.current') }}</p>
                <p class="mt-1 line-clamp-4 text-sm text-ink" data-request-current>{{ $current[$selected] ?? '—' }}</p>
            </div>
            <div class="rounded-control border border-primary/30 bg-primary-subtle p-3">
                <p class="text-xs font-medium text-ink-brand">{{ __('catalog::vendor.edit_requests.requested') }}</p>
                <p class="mt-1 line-clamp-4 text-sm text-ink" data-request-preview>—</p>
            </div>
        </div>
        <script type="application/json" data-request-current-values>@json($current, JSON_UNESCAPED_UNICODE)</script>
    @endif

    <div data-request-value-wrap>
        <x-ui::textarea name="value" :label="__('catalog::vendor.edit_requests.new_value')" :rows="3" data-request-value />
    </div>
    <x-ui::textarea name="note" :label="__('catalog::vendor.edit_requests.why')" :rows="4" :maxlength="2000" />
    <div data-request-images hidden>
        <x-ui::file-upload name="images[]" multiple accept="image/png,image/jpeg,image/webp" :label="__('catalog::vendor.edit_requests.images')" :hint="__('catalog::vendor.edit_requests.images_hint')" />
    </div>
</form>
