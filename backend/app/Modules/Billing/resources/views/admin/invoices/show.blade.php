{{--
    Admin › Finance › Invoices › Detail (ADM-054a, Figma 74:65309): number + status,
    seller / bill-to block, lines, tax, total. Print opens the print-optimised view
    (the browser's "Save as PDF" produces the PDF; no server PDF library is installed).
--}}
<x-layouts.panel surface="admin" :title="$invoice->number">
    <x-ui::page-header
        :title="$invoice->number"
        :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::invoices.title') => route('admin.finance.invoices.index'), $invoice->number => null]"
    >
        <x-slot:status>
            @include('billing::admin._status', ['status' => $invoice->status])
        </x-slot:status>
        <x-slot:actions>
            <x-ui::button variant="secondary" icon="download" :href="route('admin.finance.invoices.print', ['invoice' => $invoice->id, 'save' => 1])" target="_blank" rel="noopener">{{ __('billing::invoices.download_pdf') }}</x-ui::button>
            <x-ui::button icon="printer" :href="route('admin.finance.invoices.print', $invoice->id)" target="_blank" rel="noopener">{{ __('billing::invoices.print') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::page-header>

    <x-ui::card>
        @include('billing::admin.invoices._document', ['invoice' => $invoice])
    </x-ui::card>
</x-layouts.panel>
