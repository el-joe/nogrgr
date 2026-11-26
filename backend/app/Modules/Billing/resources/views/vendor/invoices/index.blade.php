{{--
    Vendor › Finance › Invoices (VEN-061, Figma Vendor Panel 88:110393): server-side list
    of the store's platform invoices (search, status, type, issue date) and the selected
    invoice in the side panel (VEN-061a: lines, VAT, total, PDF / Print, Pay now).
--}}
<x-vendor::layout :title="__('billing::vendor_invoices.title')" page="vendor-invoices">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('billing::vendor_invoices.title')"
            :subtitle="__('billing::vendor_invoices.subtitle')"
            :breadcrumbs="[__('billing::vendor_invoices.crumb') => null, __('billing::vendor_invoices.title') => null]"
        />

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)] 2xl:grid-cols-[minmax(0,1fr)_minmax(0,480px)]">
            <x-ui::datatable
                id="vendor-billing-invoices"
                :url="vendor_route('finance.invoices.data')"
                :columns="[
                    ['data' => 'number', 'title' => __('billing::vendor_invoices.columns.invoice'), 'searchable' => false],
                    ['data' => 'issued_at', 'title' => __('billing::vendor_invoices.columns.issued'), 'searchable' => false],
                    ['data' => 'total_minor', 'title' => __('billing::vendor_invoices.columns.amount'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'status', 'title' => __('billing::vendor_invoices.columns.status'), 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[1, 'desc']]"
                :empty-title="__('billing::vendor_invoices.empty_title')"
                :empty-description="__('billing::vendor_invoices.empty_description')"
            >
                <x-slot:filters>
                    <x-ui::filters-bar target="vendor-billing-invoices" :search-placeholder="__('billing::vendor_invoices.search')">
                        <x-ui::select name="status" :label="__('billing::vendor_invoices.filters.status')" label-hidden :options="$statuses" :placeholder="__('billing::vendor_invoices.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::vendor_invoices.filters.status')" class="w-36" />
                        <x-ui::select name="type" :label="__('billing::vendor_invoices.filters.type')" label-hidden :options="$types" :placeholder="__('billing::vendor_invoices.filters.type')" :search="false" allow-clear data-filter :data-filter-label="__('billing::vendor_invoices.filters.type')" class="w-44" />
                        <x-ui::daterange name="issued" from-name="from" to-name="to" :label="__('billing::vendor_invoices.filters.date')" label-hidden filter compact class="w-56" />
                    </x-ui::filters-bar>
                </x-slot:filters>
            </x-ui::datatable>

            <x-ui::card class="xl:sticky xl:top-24" data-invoice-panel aria-live="polite">
                <div data-invoice-panel-loading hidden class="grid gap-4" aria-busy="true">
                    <x-ui::skeleton shape="rect" class="h-8 w-1/2" />
                    <x-ui::skeleton shape="rect" class="h-5 w-2/3" />
                    <x-ui::skeleton shape="rect" class="h-40 w-full" />
                </div>
                <div data-invoice-panel-error hidden>
                    <x-ui::empty-state compact variant="error" :description="__('billing::vendor_invoices.detail.load_error')" />
                    <div class="mt-3 flex justify-center">
                        <x-ui::button variant="secondary" icon="refresh" data-invoice-panel-retry>{{ __('billing::vendor_invoices.detail.retry') }}</x-ui::button>
                    </div>
                </div>
                <div data-invoice-panel-body>
                    @if ($selected)
                        @include('billing::vendor.invoices._detail', ['invoice' => $selected, 'canPay' => $canPay])
                    @else
                        <x-ui::empty-state compact icon="file" :title="__('billing::vendor_invoices.detail.select_title')" :description="__('billing::vendor_invoices.detail.select_description')" />
                    @endif
                </div>
            </x-ui::card>
        </div>
    </div>

    @if ($canPay)
        @include('billing::vendor.invoices._pay-modal', ['gateways' => $gateways])
    @endif
</x-vendor::layout>
