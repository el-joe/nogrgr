<div class="flex min-w-0 items-center gap-3">
    @if ($sale->banner_path)
        <img src="{{ $sale->bannerUrl() }}" alt="{{ $sale->title }}" width="128" height="72" loading="lazy" class="h-[72px] w-[128px] shrink-0 rounded-control border border-line bg-subtle object-contain">
    @endif
    <div class="min-w-0">
        <p class="font-medium text-ink">{{ $sale->title }}</p>
        @if ($sale->banner_path)
            <p class="truncate text-xs text-ink-secondary">{{ __('commerce::flash_sales.banner_named', ['file' => basename($sale->banner_path)]) }}</p>
        @endif
    </div>
</div>
