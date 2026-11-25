{{--
    Owner portal › Invoice detail (OWN-005): the shared invoice document (seller, bill
    to, lines, tax, total) with download (print view → "Save as PDF") and print.
--}}
<x-layouts.panel surface="owner" :title="$invoice->number" data-page="owner-invoices">
    <x-ui::page-header
        :title="$invoice->number"
        :breadcrumbs="[__('billing::owner.invoices.title') => route('owner.invoices.index'), $invoice->number => null]"
    >
        <x-slot:status>
            @include('billing::admin._status', ['status' => $invoice->status])
        </x-slot:status>
        <x-slot:actions>
            <x-ui::button variant="secondary" icon="download" :href="route('owner.invoices.print', ['invoice' => $invoice->id, 'save' => 1])" target="_blank" rel="noopener">{{ __('billing::invoices.download_pdf') }}</x-ui::button>
            <x-ui::button icon="printer" :href="route('owner.invoices.print', $invoice->id)" target="_blank" rel="noopener">{{ __('billing::invoices.print') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::page-header>

    <x-ui::card>
        @include('billing::admin.invoices._document', ['invoice' => $invoice])
    </x-ui::card>
</x-layouts.panel>
