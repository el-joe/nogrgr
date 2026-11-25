<span class="flex items-center gap-3">
    @include('catalog::admin.partials.thumb', ['url' => $product->primaryImageUrl()])
    <span class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $product->name }}</span>
        <span class="truncate text-xs text-ink-secondary"><span dir="ltr">{{ $product->sku }}</span> · {{ trans_choice('catalog::products.variants_count', (int) $product->variants_count, ['count' => (int) $product->variants_count]) }}</span>
    </span>
</span>
