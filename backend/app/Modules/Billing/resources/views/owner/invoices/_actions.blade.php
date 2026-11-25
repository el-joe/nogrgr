{{-- Invoice row: view and print / save as PDF. --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('owner.invoices.show', $invoice->id)" :label="__('billing::owner.invoices.view', ['number' => $invoice->number])" />
    <x-ui::icon-button icon="download" size="sm" :href="route('owner.invoices.print', $invoice->id)" target="_blank" :label="__('billing::owner.invoices.download', ['number' => $invoice->number])" />
</div>
