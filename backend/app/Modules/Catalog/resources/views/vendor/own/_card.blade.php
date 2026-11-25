{{--
    Own product card (Tenant Panel Layout 12:13462 / 12:13819): image, status ribbon, name,
    summary, shopper price (UnitPricing::ownProduct — cost 0, no fixed shipping) and stock,
    edit CTA, active switch, delete. Data: $product (StoreProduct, media + variants loaded), $price (Money).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.own-products.manage');
    $image = \App\Modules\Catalog\Services\Vendor\OwnProductService::primaryImageUrl($product);
@endphp
<article class="vnd-product-card">
    <div class="vnd-product-card-media">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
        @if (! $product->is_active)
            <span class="vnd-ribbon bg-ink-secondary!">{{ __('catalog::vendor.products.hidden') }}</span>
        @elseif ($product->is_featured)
            <span class="vnd-ribbon">{{ __('catalog::vendor.products.featured') }}</span>
        @endif
        @if ($product->sale_price?->isPositive())
            <span class="vnd-price-chip">{{ __('catalog::vendor.own.on_sale') }}<br><strong class="text-base line-through" dir="ltr">{{ $product->price?->format() }}</strong></span>
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
                <span class="text-xs">{{ __('catalog::vendor.products.door_price') }}</span>
                <strong class="text-[24px] font-bold leading-[1.4] text-primary" dir="ltr">{{ $price->format() }}</strong>
                <span class="text-xs leading-[1.5]">{{ __('catalog::vendor.own.price_hint') }}</span>
            </div>
            <div class="vnd-stat-light grid content-start gap-0.5">
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.edit.stock') }}</span>
                <strong @class(['text-[22px] font-bold leading-[1.4]', 'text-danger' => (int) $product->stock <= 0]) dir="ltr">{{ number_format((int) $product->stock) }}</strong>
                <span class="text-xs text-ink-secondary">{{ trans_choice('catalog::vendor.own.variants_count', $product->variants->count(), ['count' => $product->variants->count()]) }}</span>
            </div>
        </div>
        <div class="mt-auto grid gap-3">
            @if ($canManage)
                <a href="{{ vendor_route('catalog.own-products.edit', ['product' => $product->id]) }}" class="vnd-card-cta"><x-ui::icon name="edit" :size="20" />{{ __('catalog::vendor.products.edit') }}</a>
                <div class="flex items-center justify-between gap-2">
                    <x-ui::switch :name="'own_active_'.$product->id" field="is_active" :label="__('catalog::vendor.products.active')" :checked="$product->is_active" :url="vendor_route('catalog.own-products.active', ['product' => $product->id])" />
                    <x-ui::icon-button icon="trash" size="sm" :label="__('catalog::vendor.own.delete')" :data-confirm="__('catalog::vendor.own.delete_message')" :data-confirm-title="__('catalog::vendor.own.delete_title', ['name' => $product->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('catalog.own-products.destroy', ['product' => $product->id])" />
                </div>
            @endif
        </div>
    </div>
</article>
