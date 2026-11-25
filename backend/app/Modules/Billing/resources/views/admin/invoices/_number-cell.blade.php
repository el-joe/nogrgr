<span class="grid">
    <a href="{{ route('admin.finance.invoices.show', $invoice->id) }}" class="font-medium text-ink hover:text-ink-brand" dir="ltr">{{ $invoice->number }}</a>
    @if ($invoice->status === \App\Modules\Billing\Enums\InvoiceStatus::Unpaid && $invoice->due_at)
        <span class="text-xs text-ink-secondary">{{ __('billing::invoices.due', ['date' => $invoice->due_at->translatedFormat('j M')]) }}</span>
    @endif
</span>
