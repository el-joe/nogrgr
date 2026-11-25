{{--
    Opportunity card (Tenant Panel Layout 12:12050 card): image with "Already added" ribbon,
    sales chip, name, summary, best markets (store countries the product can be sold in),
    central cost + sold count, then "Add to store" / "Already added — markets".
    Data: $product (central Product), $added (bool).
--}}
@php
    $image = $product->media->first()?->url();
@endphp
<article class="vnd-product-card">
    <div class="vnd-product-card-media">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
        @if ($added)
            <span class="vnd-ribbon">{{ __('catalog::vendor.opportunities.already') }}</span>
        @endif
        @if ($product->sold_count > 0)
            <span class="vnd-price-chip">{{ __('catalog::vendor.opportunities.sold') }}<br><strong class="text-base" dir="ltr">{{ number_format($product->sold_count) }}</strong></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid gap-1">
            <h2 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink sm:text-[26px]">{{ $product->name }}</h2>
            @if ($product->summary)
                <p class="line-clamp-3 text-sm leading-[1.5] text-ink-muted">{{ $product->summary }}</p>
            @endif
        </div>
        <div class="grid grid-cols-[1.28fr_1fr] gap-2">
            <div class="vnd-stat-dark grid content-start gap-0.5">
                <span class="text-xs">{{ __('catalog::vendor.opportunities.cost') }}</span>
                <strong class="text-[24px] font-bold leading-[1.4] text-primary" dir="ltr">{{ $product->costBasis()->format() }}</strong>
                <span class="text-xs leading-[1.5]">{{ __('catalog::vendor.opportunities.cost_hint') }}</span>
            </div>
            <div class="vnd-stat-light grid content-start gap-0.5">
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.opportunities.in_stock') }}</span>
                <strong class="text-[22px] font-bold leading-[1.4]" dir="ltr">{{ number_format($product->stock) }}</strong>
                <span class="text-xs text-ink-secondary">{{ $product->published_at?->diffForHumans() }}</span>
            </div>
        </div>
        <div class="mt-auto">
            <button type="button" class="vnd-card-cta" data-remote-modal="markets-modal" data-url="{{ vendor_route('catalog.opportunities.markets', ['central' => $product->id]) }}" @if ($added) data-added @endif>
                <x-ui::icon :name="$added ? 'globe' : 'plus'" :size="20" />{{ $added ? __('catalog::vendor.opportunities.view_markets') : __('catalog::vendor.opportunities.add') }}
            </button>
        </div>
    </div>
</article>
