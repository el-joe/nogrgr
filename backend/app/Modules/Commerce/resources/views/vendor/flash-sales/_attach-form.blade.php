{{--
    "Add to flash sale" from a product (VEN-041b, Figma Tenant Panel 87:111915): the
    product's name under the title and the store's running / upcoming sales as radios.
    Opened as a remote modal from the product pages (data-remote-modal + data-url of
    vendor.marketing.flash-sales.available-for-product).
--}}
@php
    $options = $sales->mapWithKeys(fn ($sale) => [$sale->id => $sale->starts_at->isPast() && $sale->is_active ? __('commerce::vendor_promotions.flash_sales.attach.live', ['title' => $sale->title]) : $sale->title])->all();
    $current = $sales->first(fn ($sale) => $sale->productRows->contains(fn ($row) => $row->product_source === $ref->source && (int) $row->product_id === $ref->id))?->id;
@endphp
<form
    id="flash-sale-attach-form"
    method="POST"
    action="{{ vendor_route('marketing.flash-sales.attach-product', ['product' => $product->id]) }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-modal-subtitle="{{ $productLabel }}"
    data-submit-label="{{ __('commerce::promotions.save') }}"
>
    @csrf
    @if ($options === [])
        <x-ui::empty-state compact :description="__('commerce::vendor_promotions.flash_sales.attach.none')">
            <x-ui::button icon="plus" :href="vendor_route('marketing.flash-sales.index')">{{ __('commerce::vendor_promotions.flash_sales.attach.create') }}</x-ui::button>
        </x-ui::empty-state>
    @else
        <x-ui::radio-group name="flash_sale_id" :label="__('commerce::vendor_promotions.flash_sales.attach.sale')" :options="$options" :value="$current ?? array_key_first($options)" required />
    @endif
</form>
