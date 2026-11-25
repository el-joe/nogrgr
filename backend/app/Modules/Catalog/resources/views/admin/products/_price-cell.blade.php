<span class="grid justify-items-end" dir="ltr">
    <span class="font-semibold text-ink">{{ $product->costBasis()->format() }}</span>
    @if ($product->compareAtPrice())
        <span class="text-xs text-ink-muted line-through">{{ $product->compareAtPrice()->format() }}</span>
    @endif
</span>
