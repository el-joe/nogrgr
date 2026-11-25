<a href="{{ route('admin.shipping-queue.show', $fact->id) }}" class="block font-semibold text-ink hover:text-ink-brand" dir="ltr">#{{ $fact->order_number }}</a>
<span class="text-xs text-ink-secondary">{{ trans_choice('commerce::fulfilment.queue.items', $fact->items_count, ['count' => $fact->items_count]) }} · {{ number_format($fact->weight_grams) }} g</span>
