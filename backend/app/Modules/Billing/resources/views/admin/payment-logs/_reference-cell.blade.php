<span class="grid">
    <a href="{{ route('admin.finance.payment-logs.show', $log->id) }}" class="font-medium text-ink hover:text-ink-brand" dir="ltr">{{ $log->reference() }}</a>
    <span class="text-xs text-ink-secondary">{{ $log->created_at?->translatedFormat('j M H:i') }}</span>
</span>
