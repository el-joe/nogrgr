{{-- Title + banner file of a vendor flash sale row (VEN-041); hidden tag on central rows. --}}
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
