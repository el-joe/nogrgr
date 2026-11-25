<span class="grid">
    <span class="font-medium text-ink">{{ $row->product->name }}</span>
    <span class="text-xs text-ink-secondary" dir="auto">{{ $row->variant ? ($row->variant->title ?: $row->variant->options->pluck('name')->implode(' / ')) : $row->product->sku }}@if ($row->variant?->sku) · {{ $row->variant->sku }}@endif</span>
</span>
