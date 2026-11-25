{{--
    Sample product card for the motion gallery: hover image swap, lift, quick add with
    fly-to-cart, favourite burst, blur-up image, shared-element view transition link.
    $product from MotionDemoSamples::products(); $query = extra query for the link.
--}}
@php
    $money = fn (int $amount) => 'USD '.\Illuminate\Support\Number::format($amount, 2, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<article class="mo-card group relative rounded-card bg-surface" data-product="{{ $product['slug'] }}" @if ($product['alt']) data-hover-swap @endif>
    <a href="{{ request()->fullUrlWithQuery(['product' => $product['slug']]) }}#gallery" class="block rounded-card" data-vt-image="product-{{ $product['slug'] }}">
        <div class="mo-card-media mo-media aspect-[4/5] rounded-card" style="--placeholder: url('{{ $product['image']['placeholder'] }}')">
            <img src="{{ $product['image']['src'] }}" width="{{ $product['image']['width'] }}" height="{{ $product['image']['height'] }}" alt="{{ $product['name'] }}" loading="lazy" decoding="async" draggable="false" data-blur-up data-fly-image class="h-full w-full object-cover">
            @if ($product['alt'])
                <img data-hover-image data-src="{{ $product['alt']['src'] }}" width="{{ $product['alt']['width'] }}" height="{{ $product['alt']['height'] }}" alt="" aria-hidden="true" loading="lazy" draggable="false">
            @endif
        </div>
        <div class="grid gap-1 px-1 pb-2 pt-3">
            <p class="text-xs uppercase tracking-wide text-ink-muted">{{ $product['category'] }}</p>
            <h3 class="line-clamp-1 text-sm font-medium text-ink">{{ $product['name'] }}</h3>
            <p class="flex items-baseline gap-2 text-sm" dir="ltr">
                <span class="font-semibold text-ink">{{ $money($product['price']) }}</span>
                @if ($product['compare'])
                    <span class="text-xs text-ink-muted line-through"><span class="sr-only">{{ __('platform::motion-demo.card.was') }}</span>{{ $money($product['compare']) }}</span>
                @endif
            </p>
        </div>
    </a>
    <button type="button" data-favorite aria-pressed="false" class="mo-press absolute end-3 top-3 grid size-9 place-items-center rounded-full bg-surface/90 text-primary shadow-card" aria-label="{{ __('platform::motion-demo.card.favorite', ['name' => $product['name']]) }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="mo-heart-fill" d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>
    <div class="mo-quick-add absolute inset-x-3 bottom-[88px]">
        <button type="button" data-add-to-cart class="mo-press flex h-10 w-full items-center justify-center gap-2 rounded-full bg-primary px-4 text-sm font-medium text-on-primary shadow-card">
            <span class="mo-state mo-state-idle items-center gap-2"><x-ui::icon name="cart" :size="16" />{{ __('platform::motion-demo.card.add') }}</span>
            <span class="mo-state mo-state-loading items-center gap-2"><x-ui::icon name="refresh" :size="16" class="mo-spin" />{{ __('platform::motion-demo.card.adding') }}</span>
            <span class="mo-state mo-state-done items-center gap-2"><x-ui::icon name="check" :size="16" />{{ __('platform::motion-demo.card.added') }}</span>
        </button>
    </div>
</article>
