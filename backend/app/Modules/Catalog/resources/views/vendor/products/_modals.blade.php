{{--
    Shared modals of the products pages: price list (VEN-010a, remote body), bulk markup
    (selected cards), shipping details (VEN-010e) and request a central edit (VEN-013a).
--}}
<x-ui::modal id="price-list-modal" :title="__('catalog::vendor.price_list.title')" size="lg">
    @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.price_list.load_error')])
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor.cancel') }}</x-ui::button>
        @if ($canManage)
            <x-ui::button type="submit" form="price-list-form" icon="check" data-remote-submit>{{ __('catalog::vendor.save') }}</x-ui::button>
        @endif
    </x-slot:footer>
</x-ui::modal>

@if ($canManage)
    <x-ui::modal id="bulk-markup-modal" :title="__('catalog::vendor.price_list.bulk_title')" :subtitle="__('catalog::vendor.price_list.bulk_subtitle')">
        <form id="bulk-markup-form" class="grid gap-4" data-price-list-form data-apply-url="{{ vendor_route('catalog.products.price-list.store') }}" data-bulk-target="store-products" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::select name="profit_type" :label="__('catalog::vendor.price_list.mode')" :options="\App\Modules\Commerce\Enums\ProfitType::options()" selected="percentage" :search="false" required />
                <x-ui::input name="profit_value" type="number" step="0.01" min="0" :label="__('catalog::vendor.price_list.markup')" required dir="ltr" inputmode="decimal" />
            </div>
            @include('catalog::vendor.products._run-progress')
        </form>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="bulk-markup-form" icon="check">{{ __('catalog::vendor.price_list.apply') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif

<x-ui::modal id="shipping-modal" :title="__('catalog::vendor.shipping.title')" :subtitle="__('catalog::vendor.shipping.subtitle')">
    @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.shipping.load_error')])
</x-ui::modal>

@if ($canManage)
    <x-ui::modal id="edit-request-modal" :title="__('catalog::vendor.edit_requests.modal_title')">
        @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.edit_requests.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="edit-request-form" icon="upload" data-remote-submit>{{ __('catalog::vendor.edit_requests.submit') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
