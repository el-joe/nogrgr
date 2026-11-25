{{-- New product / brand request opened by URL (no modal): the same form in a page card. --}}
<x-vendor::layout :title="__('catalog::vendor_requests.new.'.$kind->value)" page="vendor-requests">
    <div class="mx-auto grid w-full max-w-3xl gap-6" data-success-url="{{ vendor_route('requests.'.$kind->value.'.index') }}">
        <x-ui::page-header
            :title="__('catalog::vendor_requests.new.'.$kind->value)"
            :subtitle="$kind === \App\Modules\Catalog\Enums\RequestKind::Brand ? __('catalog::vendor_requests.create.brand_subtitle') : __('catalog::vendor_requests.create.product_subtitle')"
            :breadcrumbs="[__('catalog::vendor_requests.title') => vendor_route('requests.index'), __('catalog::vendor_requests.tabs.'.$kind->value) => vendor_route('requests.'.$kind->value.'.index'), __('catalog::vendor_requests.new.'.$kind->value) => null]"
        />
        <x-ui::card>
            @include('catalog::vendor.requests._form')
            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-ui::button type="submit" form="vendor-request-form" icon="plus">{{ __('catalog::vendor_requests.create.submit') }}</x-ui::button>
                <x-ui::button variant="secondary" :href="vendor_route('requests.'.$kind->value.'.index')">{{ __('catalog::vendor_requests.create.cancel') }}</x-ui::button>
            </div>
        </x-ui::card>
    </div>
</x-vendor::layout>
