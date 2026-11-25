{{-- New central product card (VEN-011): image, name, summary, central price, preview + add. --}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.products.manage');
    $image = $product->media->first()?->url();
@endphp
<article class="vnd-product-card">
    <div class="vnd-product-card-media">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
        <span class="vnd-ribbon bg-primary!">{{ __('catalog::vendor.new.badge') }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid gap-1">
            <h2 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink">{{ $product->name }}</h2>
            @if ($product->summary)
                <p class="line-clamp-3 text-sm leading-[1.5] text-ink-muted">{{ $product->summary }}</p>
            @endif
        </div>
        <div class="vnd-stat-light flex items-center justify-between gap-3">
            <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.new.cost') }}</span>
            <strong class="text-lg font-bold text-ink" dir="ltr">{{ $product->costBasis()->format() }}</strong>
        </div>
        <div class="mt-auto grid grid-cols-2 gap-2">
            <x-ui::button variant="secondary" icon="eye" data-remote-modal="new-preview-modal" :data-url="vendor_route('catalog.products.new.show', ['central' => $product->id])" :data-preview-id="$product->id">{{ __('catalog::vendor.new.preview') }}</x-ui::button>
            @if ($canManage)
                <x-ui::button icon="plus" data-import-one :data-url="vendor_route('catalog.products.new.import')" :data-id="$product->id">{{ __('catalog::vendor.new.add') }}</x-ui::button>
            @endif
        </div>
    </div>
</article>
