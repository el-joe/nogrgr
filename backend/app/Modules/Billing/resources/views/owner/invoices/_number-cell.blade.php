{{-- Invoice number + issue date. --}}
<span class="grid">
    <a href="{{ route('owner.invoices.show', $invoice->id) }}" class="font-medium text-ink hover:text-ink-brand" dir="ltr">{{ $invoice->number }}</a>
    <span class="text-xs text-ink-secondary">{{ __('billing::owner.invoices.issued', ['date' => $invoice->issued_at?->translatedFormat('j M Y') ?? '—']) }}</span>
</span>
