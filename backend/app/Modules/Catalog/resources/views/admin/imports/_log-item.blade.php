{{-- Log "Item" cell: product name + source SKU. @param \App\Modules\Catalog\Models\CatalogImportLog $log --}}
<div class="grid min-w-0 gap-0.5">
    @if ($log->product_id !== null && auth('admin')->user()?->can('catalog.products.manage'))
        <a href="{{ route('admin.catalog.products.edit', $log->product_id) }}" class="truncate font-medium text-ink hover:text-ink-brand">{{ $log->item_name ?? '—' }}</a>
    @else
        <span class="truncate font-medium text-ink">{{ $log->item_name ?? '—' }}</span>
    @endif
    @if ($log->sku)
        <bdi dir="ltr" class="truncate text-xs text-ink-secondary">{{ $log->sku }}</bdi>
    @endif
</div>
