{{-- Banner thumbnail + title + banner file of a vendor flash sale row (VEN-041); hidden tag on central rows. --}}
<div class="flex min-w-0 items-center gap-3">
    @if ($sale->bannerUrl)
        <img src="{{ $sale->bannerUrl }}" alt="{{ $sale->title }}" width="128" height="72" loading="lazy" @class(['h-[72px] w-[128px] shrink-0 rounded-control border border-line bg-subtle object-contain', 'opacity-50' => $sale->hidden])>
    @endif
    <div class="min-w-0">
        <p class="flex items-center gap-2 font-medium text-ink">
            <span>{{ $sale->title }}</span>
            @if ($sale->hidden)
                <x-ui::badge tone="neutral" :title="__('commerce::vendor_promotions.hidden_hint')">{{ __('commerce::vendor_promotions.hidden') }}</x-ui::badge>
            @endif
        </p>
        @if ($sale->bannerUrl)
            <p class="truncate text-xs text-ink-secondary">{{ __('commerce::flash_sales.banner_named', ['file' => basename(parse_url($sale->bannerUrl, PHP_URL_PATH) ?: $sale->bannerUrl)]) }}</p>
        @endif
    </div>
</div>
