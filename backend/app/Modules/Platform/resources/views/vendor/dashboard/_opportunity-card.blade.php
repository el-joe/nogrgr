{{--
    One dashboard opportunity (VEN-009c, Dashboard 12:11267 card): a best-selling offered
    central product. Added → "Add to flash sale" (existing VEN-041b modal, store product
    id); not added → "Add to store" (existing VEN-011 import). Data: $product (central
    Product), $storeProductId (int|null), $widgets.
--}}
@php
    $image = $product->media->first()?->url();
    $added = $storeProductId !== null;
@endphp
<article class="vnd-product-card vnd-rail-item" data-dashboard-card>
    <div class="vnd-product-card-media">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
        <span class="vnd-ribbon" @if (! $added) hidden @endif data-added-ribbon>{{ __('platform::dashboard_vendor.opportunities.already') }}</span>
        @if ($product->sold_count > 0)
            <span class="vnd-price-chip">{{ __('platform::dashboard_vendor.opportunities.sold') }}<br><strong class="text-base" dir="ltr">{{ number_format($product->sold_count) }}</strong></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid gap-1">
            <h3 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink">{{ $product->name }}</h3>
            @if ($product->summary)
                <p class="line-clamp-2 text-sm leading-[1.5] text-ink-muted">{{ $product->summary }}</p>
            @endif
        </div>
        <div class="grid grid-cols-[1.28fr_1fr] gap-2">
            <div class="vnd-stat-dark grid content-start gap-0.5">
                <span class="text-xs">{{ __('platform::dashboard_vendor.opportunities.cost') }}</span>
                <strong class="text-[22px] font-bold leading-[1.4] text-primary" dir="ltr">{{ $product->costBasis()->format() }}</strong>
                <span class="text-xs leading-[1.5]">{{ __('platform::dashboard_vendor.opportunities.cost_hint') }}</span>
            </div>
            <div class="vnd-stat-light grid content-start gap-0.5">
                <span class="text-xs text-ink-secondary">{{ __('platform::dashboard_vendor.opportunities.stock') }}</span>
                <strong class="text-[22px] font-bold leading-[1.4]" dir="ltr">{{ number_format($product->stock) }}</strong>
            </div>
        </div>
        <div class="mt-auto">
            @if ($added && $widgets->can('flash_sale'))
                <button type="button" class="vnd-card-cta" data-added data-remote-modal="flash-sale-attach-modal" data-url="{{ vendor_route('marketing.flash-sales.available-for-product', ['product' => $storeProductId]) }}"><x-ui::icon name="zap" :size="20" />{{ __('platform::dashboard_vendor.opportunities.add_to_flash_sale') }}</button>
            @elseif (! $added && $widgets->can('import'))
                <button type="button" class="vnd-card-cta" data-dashboard-import data-url="{{ vendor_route('catalog.products.new.import') }}" data-id="{{ $product->id }}"><x-ui::icon name="plus" :size="20" /><span data-label>{{ __('platform::dashboard_vendor.opportunities.add_to_store') }}</span></button>
            @endif
        </div>
    </div>
</article>
