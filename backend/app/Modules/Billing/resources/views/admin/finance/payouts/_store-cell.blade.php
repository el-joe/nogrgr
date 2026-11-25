{{-- Payout row store: name + bank on file (masked IBAN) or the method. --}}
<span class="grid min-w-0">
    <span class="truncate font-medium text-ink">{{ $payout->tenant?->getAttribute('name') ?? '—' }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ ($payout->payee['iban'] ?? null) ? 'IBAN '.$payout->payee['iban'] : $payout->method->label() }}</span>
</span>
