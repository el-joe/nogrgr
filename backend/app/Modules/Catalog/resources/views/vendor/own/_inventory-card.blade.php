{{--
    Inventory card (VEN-016b, Tenant Panel Layout 12:17880): own product with an inline stock
    editor (product stock, or one field per variant). Data: $product, $price.
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
        @if ((int) $product->stock <= 0)
            <span class="vnd-ribbon bg-danger!">{{ __('catalog::enums.store_product_filter.out_of_stock') }}</span>
        @elseif ($product->min_stock > 0 && $product->stock <= $product->min_stock)
            <span class="vnd-ribbon bg-warning!">{{ __('catalog::vendor.own.low_stock') }}</span>
        @endif
    </div>
    <form method="POST" action="{{ vendor_route('catalog.own-products.stock', ['product' => $product->id]) }}" class="flex flex-1 flex-col gap-4 p-4" data-ajax data-stock-form novalidate>
        @csrf
        @method('PATCH')
        <div class="grid gap-1">
            <h2 class="line-clamp-2 text-xl font-bold leading-[1.4] text-ink">{{ $product->name }}</h2>
            <p class="text-sm text-ink-secondary" dir="ltr">{{ $product->sku }} · {{ $price->format() }}</p>
        </div>
        @if ($product->variants->isEmpty())
            <x-ui::input :name="'stock'" :id="'stock-'.$product->id" type="number" min="0" :label="__('catalog::vendor.edit.stock')" :value="$product->stock" :disabled="! $canManage" dir="ltr" inputmode="numeric" />
        @else
            <div class="grid gap-2">
                @foreach ($product->variants as $variant)
                    <x-ui::input :name="'variants['.$variant->id.']'" :id="'stock-'.$product->id.'-'.$variant->id" type="number" min="0" :label="$variant->title" :value="$variant->stock" :disabled="! $canManage" dir="ltr" inputmode="numeric" />
                @endforeach
            </div>
        @endif
        @if ($canManage)
            <button type="submit" class="vnd-card-cta mt-auto"><x-ui::icon name="check" :size="20" />{{ __('catalog::vendor.own.save_stock') }}</button>
        @endif
    </form>
</article>
