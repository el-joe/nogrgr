{{--
    Host modal of "Add to flash sale" (VEN-041b) for the vendor product pages (page module
    vendor-catalog binds remote modals). Include once per page and open it with:
    <x-ui::button data-remote-modal="flash-sale-attach-modal" :data-url="vendor_route('marketing.flash-sales.available-for-product', ['product' => $product->id])">
--}}
@if (auth('vendor')->user()?->can('flash-sales.manage'))
    <x-ui::modal id="flash-sale-attach-modal" size="sm" :title="__('commerce::vendor_promotions.flash_sales.attach.title')" subtitle=" ">
        @include('commerce::admin.partials.remote-states')
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::promotions.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="flash-sale-attach-form" data-remote-submit>{{ __('commerce::promotions.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
