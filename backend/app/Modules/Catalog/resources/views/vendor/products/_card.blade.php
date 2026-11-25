{{--
    Imported product card (Tenant Panel Layout 12:13010): image, status ribbon, profit chip,
    name, summary, "cost to your customer's door" (cheapest destination, Commerce\Pricing)
    and the store profit, then the edit CTA and the store actions (active / featured
    switches, price list, shipping details, remove).
    Data: $product (StoreProduct), $view (StoreProductView), $cheapest (StorePriceRow|null).
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('catalog.products.manage');
@endphp
<article class="vnd-product-card" data-product-card="{{ $product->id }}">
    <div class="vnd-product-card-media">
        @if ($view->primaryImageUrl())
            <img src="{{ $view->primaryImageUrl() }}" alt="{{ $view->name }}" loading="lazy" decoding="async">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
        @if (! $view->available)
            <span class="vnd-ribbon bg-danger!">{{ __('catalog::vendor.products.unavailable') }}</span>
        @elseif (! $product->is_active)
            <span class="vnd-ribbon bg-ink-secondary!">{{ __('catalog::vendor.products.hidden') }}</span>
        @elseif ($product->is_featured)
            <span class="vnd-ribbon">{{ __('catalog::vendor.products.featured') }}</span>
        @endif
        @if ($cheapest)
            <span class="vnd-price-chip">{{ __('catalog::vendor.products.margin_chip') }}<br><strong class="text-base" dir="ltr">{{ number_format($cheapest->marginPercent(), 1) }}%</strong></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid gap-1">
            <h2 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink sm:text-[26px]">{{ $view->name }}</h2>
            @if ($view->summary)
                <p class="line-clamp-3 text-sm leading-[1.5] text-ink-muted">{{ $view->summary }}</p>
            @endif
            <p class="text-xs text-ink-muted" dir="ltr">{{ $view->sku }}</p>
        </div>
        <div class="grid grid-cols-[1.28fr_1fr] gap-2">
            <div class="vnd-stat-dark grid content-start gap-0.5">
                <span class="text-xs">{{ __('catalog::vendor.products.door_price') }}</span>
                <strong class="text-[24px] font-bold leading-[1.4] text-primary" dir="ltr">{{ $cheapest?->price->format() ?? '—' }}</strong>
                <span class="text-xs leading-[1.5]">{{ __('catalog::vendor.products.door_price_hint') }}</span>
            </div>
            <div class="vnd-stat-light grid content-start gap-0.5">
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.products.your_profit') }}</span>
                <strong class="text-[22px] font-bold leading-[1.4]" dir="ltr">{{ $cheapest?->profit->format() ?? '—' }}</strong>
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.products.stock', ['count' => number_format($view->stock)]) }}</span>
            </div>
        </div>
        @if ($cheapest === null)
            <p class="text-xs text-warning">{{ __('catalog::vendor.products.no_countries') }}</p>
        @endif
        <div class="mt-auto grid gap-3">
            <a href="{{ vendor_route('catalog.products.edit', ['product' => $product->id]) }}" class="vnd-card-cta"><x-ui::icon name="edit" :size="20" />{{ __('catalog::vendor.products.edit') }}</a>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui::switch :name="'is_active_'.$product->id" field="is_active" :label="__('catalog::vendor.products.active')" :checked="$product->is_active" :disabled="! $canManage" :url="$canManage ? vendor_route('catalog.products.active', ['product' => $product->id]) : null" />
                    <x-ui::switch :name="'is_featured_'.$product->id" field="is_featured" :label="__('catalog::vendor.products.featured')" :checked="$product->is_featured" :disabled="! $canManage" :url="$canManage ? vendor_route('catalog.products.featured', ['product' => $product->id]) : null" />
                </div>
                <x-ui::row-actions :label="__('catalog::vendor.products.actions', ['name' => $view->name])">
                    <x-ui::dropdown-item icon="percent" data-remote-modal="price-list-modal" :data-url="vendor_route('catalog.products.price-list', ['product' => $product->id])">{{ __('catalog::vendor.price_list.open') }}</x-ui::dropdown-item>
                    <x-ui::dropdown-item icon="truck" data-remote-modal="shipping-modal" :data-url="vendor_route('catalog.products.shipping', ['product' => $product->id])">{{ __('catalog::vendor.shipping.open') }}</x-ui::dropdown-item>
                    @if ($canManage)
                        <x-ui::dropdown-item icon="message" data-remote-modal="edit-request-modal" :data-url="vendor_route('catalog.edit-requests.form', ['product' => $product->id])">{{ __('catalog::vendor.edit_requests.request_change') }}</x-ui::dropdown-item>
                        <x-ui::dropdown-divider />
                        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::vendor.products.remove_message')" :data-confirm-title="__('catalog::vendor.products.remove_title', ['name' => $view->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('catalog.products.destroy', ['product' => $product->id])">{{ __('catalog::vendor.products.remove') }}</x-ui::dropdown-item>
                    @endif
                </x-ui::row-actions>
            </div>
        </div>
    </div>
</article>
