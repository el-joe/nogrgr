<div class="min-w-0">
    <p class="font-medium text-ink">{{ $sale->title }}</p>
    @if ($sale->banner_path)
        <p class="truncate text-xs text-ink-secondary">{{ __('commerce::flash_sales.banner_named', ['file' => basename($sale->banner_path)]) }}</p>
    @endif
</div>
