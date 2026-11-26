{{-- Subscriptions history row: the subscription's latest invoice (print link) or none. --}}
@if ($invoice)
    <a href="{{ vendor_route('finance.subscription.invoices.print', ['invoice' => $invoice->id]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-ink-brand hover:underline" dir="ltr">
        <x-ui::icon name="printer" :size="14" />{{ $invoice->number }}
    </a>
@else
    <span class="text-ink-muted">{{ __('billing::vendor_subscription.history.no_invoice') }}</span>
@endif
