{{-- Invoice row (VEN-061): view in the side panel, print, save as PDF, pay (unpaid + finance.subscription.manage). --}}
<div class="flex items-center justify-end gap-1">
    @if ($canPay && $invoice->status === \App\Modules\Billing\Enums\InvoiceStatus::Unpaid)
        <x-ui::button size="sm" icon="credit-card"
            data-invoice-pay
            :data-invoice-id="$invoice->id"
            :data-invoice-number="$invoice->number"
            :data-invoice-amount="$invoice->total()->format()"
        >{{ __('billing::vendor_invoices.actions.pay') }}</x-ui::button>
    @endif
    <x-ui::icon-button icon="eye" size="sm" :href="vendor_route('finance.invoices.show', ['invoice' => $invoice->id])" data-invoice-view :data-url="vendor_route('finance.invoices.show', ['invoice' => $invoice->id])" :label="__('billing::vendor_invoices.actions.view', ['number' => $invoice->number])" />
    <x-ui::icon-button icon="printer" size="sm" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id])" target="_blank" rel="noopener" :label="__('billing::vendor_invoices.actions.print_named', ['number' => $invoice->number])" />
    <x-ui::icon-button icon="download" size="sm" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id, 'save' => 1])" target="_blank" rel="noopener" :label="__('billing::vendor_invoices.actions.pdf_named', ['number' => $invoice->number])" />
</div>
