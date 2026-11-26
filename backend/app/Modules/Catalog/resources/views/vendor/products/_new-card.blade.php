{{--
    New central product card (VEN-011) — client "New in products" grid 12:13819 (decision #101):
    image (object-contain) with the purple ribbon, Bold 22 name, summary, the dark price box
    (central price, before markup and shipping) and the light stock box, then the preview
    square + the purple full-width "Add to store" (client "Create your Ad" slot).
--}}
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
        <span class="vnd-ribbon">{{ __('catalog::vendor.new.badge') }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid gap-1">
            <h2 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink sm:text-[24px]">{{ $product->name }}</h2>
            @if ($product->summary)
                <p class="line-clamp-3 text-sm leading-[1.5] text-ink-muted">{{ $product->summary }}</p>
            @endif
        </div>
        <div class="grid grid-cols-[1.28fr_1fr] gap-2">
            <div class="vnd-stat-dark grid content-start gap-0.5">
                <span class="text-xs">{{ __('catalog::vendor.new.cost') }}</span>
                <strong class="text-[24px] font-bold leading-[1.4] text-primary" dir="ltr">{{ $product->costBasis()->format() }}</strong>
                <span class="text-xs leading-[1.5]">{{ __('catalog::vendor.new.cost_hint') }}</span>
            </div>
            <div class="vnd-stat-light grid content-start gap-0.5">
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.new.stock') }}</span>
                <strong class="text-[22px] font-bold leading-[1.4]" dir="ltr">{{ number_format((int) $product->stock) }}</strong>
                <span class="text-xs text-ink-secondary">{{ __('catalog::vendor.new.stock_hint') }}</span>
            </div>
        </div>
        <div class="mt-auto flex gap-2">
            <x-ui::button variant="secondary" size="lg" icon="eye" class="ui-btn-square w-12 shrink-0" data-remote-modal="new-preview-modal" :data-url="vendor_route('catalog.products.new.show', ['central' => $product->id])" :data-preview-id="$product->id" :aria-label="__('catalog::vendor.new.preview_named', ['name' => $product->name])" :title="__('catalog::vendor.new.preview')"><span class="sr-only">{{ __('catalog::vendor.new.preview') }}</span></x-ui::button>
            @if ($canManage)
                <x-ui::button size="lg" icon="plus" class="vnd-card-cta-ad flex-1" data-import-one :data-url="vendor_route('catalog.products.new.import')" :data-id="$product->id">{{ __('catalog::vendor.new.add') }}</x-ui::button>
            @endif
        </div>
    </div>
</article>
