{{--
    Vendor › Products › Edit requests (VEN-013, Vendor Panel (new screens) 83:102290): history
    of the store's requests to change central data (search, status), detail modal, and
    "New request" (VEN-013a) with a product picker.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.products.manage');
@endphp
<x-vendor::layout :title="__('catalog::vendor.edit_requests.title')" page="vendor-catalog">
    <x-ui::page-header
        :title="__('catalog::vendor.edit_requests.title')"
        :subtitle="__('catalog::vendor.edit_requests.subtitle')"
        :breadcrumbs="[__('catalog::vendor.products.title') => vendor_route('catalog.products.index'), __('catalog::vendor.edit_requests.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" data-remote-modal="edit-request-modal" :data-url="vendor_route('catalog.edit-requests.form')">{{ __('catalog::vendor.edit_requests.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="edit-requests"
        :url="vendor_route('catalog.edit-requests.data')"
        :columns="[
            ['data' => 'request', 'title' => __('catalog::vendor.edit_requests.columns.request')],
            ['data' => 'product', 'title' => __('catalog::vendor.edit_requests.columns.product'), 'orderable' => false],
            ['data' => 'fields', 'title' => __('catalog::vendor.edit_requests.columns.fields'), 'orderable' => false],
            ['data' => 'status', 'title' => __('catalog::vendor.edit_requests.columns.status')],
            ['data' => 'updated_at', 'title' => __('catalog::vendor.edit_requests.columns.updated')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'desc']]"
        :empty-title="__('catalog::vendor.edit_requests.empty_title')"
        :empty-description="__('catalog::vendor.edit_requests.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="edit-requests" :search-placeholder="__('catalog::vendor.edit_requests.search')">
                <x-ui::select name="request_status" :label="__('catalog::vendor.filters.status')" label-hidden :options="\App\Modules\Catalog\Enums\EditRequestStatus::options()" :placeholder="__('catalog::vendor.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor.filters.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>

    <x-ui::modal id="edit-request-detail" :title="__('catalog::vendor.edit_requests.title')" size="lg">
        @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.edit_requests.load_error')])
    </x-ui::modal>

    @include('catalog::vendor.products._modals', ['canManage' => $canManage])
</x-vendor::layout>
