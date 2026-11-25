{{--
    New Manufacturing Request (VEN-050e, Figma 12:16096): product name, quantity,
    description and an optional link to one of the store's products (search).
--}}
<x-vendor::layout :title="__('catalog::vendor_requests.create.manufacturing_title')" page="vendor-requests">
    <div class="grid gap-6" data-success-url="{{ vendor_route('requests.manufacturing.index') }}">
        <x-ui::page-header
            :title="__('catalog::vendor_requests.create.manufacturing_title')"
            :subtitle="__('catalog::vendor_requests.create.manufacturing_subtitle')"
            :breadcrumbs="[__('catalog::vendor_requests.title') => vendor_route('requests.index'), __('catalog::vendor_requests.tabs.manufacturing') => vendor_route('requests.manufacturing.index'), __('catalog::vendor_requests.create.manufacturing_title') => null]"
        />
        <x-ui::card>
            <form id="vendor-request-form" method="POST" action="{{ vendor_route('requests.manufacturing.store') }}" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2" data-ajax novalidate>
                @csrf
                <x-ui::input name="subject" :label="__('catalog::vendor_requests.fields.subject_manufacturing')" :placeholder="__('catalog::vendor_requests.fields.subject_manufacturing_placeholder')" maxlength="255" required />
                <x-ui::input name="quantity" type="number" min="1" step="1" :label="__('catalog::vendor_requests.fields.quantity')" placeholder="0" required dir="ltr" inputmode="numeric" />
                <x-ui::textarea name="notes" :label="__('catalog::vendor_requests.fields.description')" :placeholder="__('catalog::vendor_requests.fields.description_placeholder')" maxlength="5000" rows="7" required />
                <div class="grid content-start gap-4">
                    <x-ui::select name="product_id" :label="__('catalog::vendor_requests.fields.product')" :ajax-url="vendor_route('requests.manufacturing.products')" :placeholder="__('catalog::vendor_requests.fields.product_placeholder')" allow-clear />
                    <x-ui::file-upload name="files" multiple :label="__('catalog::vendor_requests.fields.files')" :hint="__('catalog::vendor_requests.fields.files_hint')" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" />
                </div>
                <div class="flex flex-wrap gap-3 border-t border-line pt-5 lg:col-span-2">
                    <x-ui::button type="submit" icon="plus">{{ __('catalog::vendor_requests.create.submit') }}</x-ui::button>
                    <x-ui::button variant="secondary" :href="vendor_route('requests.manufacturing.index')">{{ __('catalog::vendor_requests.create.cancel') }}</x-ui::button>
                </div>
            </form>
        </x-ui::card>
    </div>
</x-vendor::layout>
