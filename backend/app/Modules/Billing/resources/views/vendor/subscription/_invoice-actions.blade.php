{{-- Invoice row: pay (unpaid, manage permission) and print / save as PDF. --}}
<div class="flex items-center justify-end gap-1">
    @if ($canPay && $invoice->status === \App\Modules\Billing\Enums\InvoiceStatus::Unpaid)
        <x-ui::button size="sm" icon="credit-card"
            data-invoice-pay
            :data-invoice-id="$invoice->id"
            :data-invoice-number="$invoice->number"
            :data-invoice-amount="$invoice->total()->format()"
        >{{ __('billing::vendor_subscription.actions.pay_now') }}</x-ui::button>
    @endif
    <x-ui::icon-button icon="printer" size="sm" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id])" target="_blank" rel="noopener" :label="__('billing::vendor_subscription.actions.print_invoice', ['number' => $invoice->number])" />
    <x-ui::icon-button icon="download" size="sm" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id, 'save' => 1])" target="_blank" rel="noopener" :label="__('billing::vendor_subscription.actions.download_invoice', ['number' => $invoice->number])" />
</div>
