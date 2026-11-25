<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.finance.invoices.show', $invoice->id)" :label="__('billing::invoices.view')" />
    <x-ui::icon-button icon="printer" size="sm" :href="route('admin.finance.invoices.print', $invoice->id)" target="_blank" rel="noopener" :label="__('billing::invoices.print')" />
</div>
