{{-- Invoice number + what it is for (first line, else the type). --}}
<div class="grid min-w-0 gap-0.5">
    <span class="font-medium text-ink" dir="ltr">{{ $invoice->number }}</span>
    <span class="truncate text-xs text-ink-secondary">{{ $invoice->items->first()?->description ?? $invoice->type->label() }}</span>
</div>
