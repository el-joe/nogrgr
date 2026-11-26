{{--
    Ecommet product card. variant="grid" (Figma 1:1089: image, name, compare + price,
    stars, star-seller chip, cart button; optional "Best-Selling" ribbon or a red tag line)
    or variant="deal" (1:1067 lightning deals: red price, compare, -% chip, flash-sale
    progress + countdown, stars + review count).
    The cart button posts to the Store API (storefront/catalog/cart.js); products with
    variants link to the product page instead. tag: best|new|rating|discount|null.
--}}
@props(['card', 'variant' => 'grid', 'ribbon' => false, 'tag' => null, 'rank' => null, 'eager' => false])
@if (\App\Modules\Storefront\Support\StorefrontTheme::family() === 'elora')
    {{-- Elora family: its own card (Figma Elora 1:22460 family); same data and JS hooks. --}}
    <x-sf::elora.product-card :card="$card" :eager="$eager" :rank="$rank" {{ $attributes }} />
@elseif (\App\Modules\Storefront\Support\StorefrontTheme::family() === 'souqify')
    {{-- Souqify family: its own card (Figma Souqify Deal Card 1:80148); same data and JS hooks. --}}
    <x-sf::souqify.product-card :card="$card" :variant="in_array($variant, ['wide', 'side', 'deal'], true) ? $variant : 'card'" :eager="$eager" :rank="$rank" {{ $attributes }} />
@else
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductCardData $card */
    $deal = $variant === 'deal';
    $stars = (int) round($card->rating);
    $tagText = match ($tag) {
        'best' => __st('catalog.card.best_selling_tag'),
        'new' => __st('catalog.card.new'),
        'rating' => $card->reviewCount > 0 ? __st('catalog.card.rating', ['rating' => number_format($card->rating, 1)]) : null,
        'discount' => $card->discount ? __st('catalog.card.discount_off', ['percent' => $card->discount]) : null,
        default => null,
    };
@endphp
<article {{ $attributes->class(['eco-card', 'eco-card-deal' => $deal]) }} data-product="{{ $card->id }}">
    @if ($ribbon)
        <span class="eco-ribbon">{{ $rank ? __st('catalog.card.rank', ['rank' => $rank]) : __st('catalog.card.best_selling') }}</span>
    @endif
    <div class="eco-card-img">
        @if ($card->image)
            <img src="{{ $card->image }}" alt="{{ $card->name }}" width="222" height="164" loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif decoding="async" data-fly-image>
            @if ($card->image2 && ! $deal)
                <img src="{{ $card->image2 }}" alt="" width="222" height="164" loading="lazy" decoding="async" aria-hidden="true">
            @endif
        @else
            <x-ui::icon name="image" :size="40" class="text-eco-semi" data-fly-image />
        @endif
        @unless ($card->inStock)
            <span class="absolute inset-x-0 bottom-0 bg-eco-black/75 py-1 text-center text-xs text-white">{{ __st('catalog.card.out_of_stock') }}</span>
        @endunless
    </div>
    <div class="eco-card-body flex-1">
        @if ($deal)
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5 whitespace-nowrap max-lg:gap-x-1.5">
                    <span class="text-base font-medium text-eco-red max-lg:text-sm">{{ $card->price }}</span>
                    @if ($card->compare)<span class="eco-compare text-xs max-lg:text-[10px]"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
                </p>
                @if ($card->discount)<span class="eco-discount">-{{ $card->discount }}%</span>@endif
            </div>
            @if ($card->flash && $card->flash['ends_at'])
                <div class="flex items-center justify-between gap-2">
                    <span class="eco-flash-track" data-progress="{{ $card->flashProgress() }}" data-progress-start="{{ $card->flash['starts_at'] }}" data-progress-end="{{ $card->flash['ends_at'] }}" aria-hidden="true">
                        <span class="w-[var(--p,0%)]"></span>
                        <i class="start-[var(--p,0%)]"><x-ui::icon name="timer" :size="12" /></i>
                    </span>
                    <span class="eco-countdown text-xs tracking-[0.5px] text-eco-black" data-countdown="{{ $card->flash['ends_at'] }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}" data-countdown-compact>
                        <span data-unit="days">00</span>:<span data-unit="hours">00</span>:<span data-unit="minutes">00</span>:<span data-unit="seconds">00</span>
                    </span>
                </div>
            @endif
            <a href="{{ $card->url }}" class="absolute inset-0 rounded-lg" aria-label="{{ $card->name }}"></a>
            <div class="flex items-center gap-1.5">
                <x-sf::stars :rating="$card->rating" />
                @if ($card->reviewCount > 0)<span class="text-xs tracking-[0.5px] text-eco-gray">{{ $card->reviewCount }}</span>@endif
            </div>
        @else
            <a href="{{ $card->url }}" class="eco-card-name" title="{{ $card->name }}">{{ $card->name }}</a>
            <p class="flex flex-wrap items-center gap-x-2 whitespace-nowrap">
                @if ($card->compare)<span class="eco-compare"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
                <span class="eco-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span>{{ $card->price }}</span>
            </p>
            @if ($tagText)
                <p class="text-xs tracking-[0.5px] text-[#ff3b2f]">{{ $tagText }}</p>
            @endif
            <div class="flex items-end justify-between gap-2">
                <div class="grid gap-2">
                    <x-sf::stars :rating="$card->rating" :tone="$tag === 'best' ? 'dark' : 'yellow'" />
                    @if ($card->starSeller)
                        <span class="eco-star-seller w-fit"><x-ui::icon name="star" :size="10" class="fill-current" />{{ __st('catalog.card.star_seller') }}</span>
                    @endif
                </div>
                @if ($card->hasVariants || ! $card->inStock)
                    <a href="{{ $card->url }}" class="eco-card-cart mo-press" aria-label="{{ $card->inStock ? __st('catalog.card.choose_options', ['name' => $card->name]) : __st('catalog.card.view', ['name' => $card->name]) }}">
                        <x-ui::icon name="cart-add" :size="24" />
                    </a>
                @else
                    <button type="button" class="eco-card-cart" data-add-to-cart data-product-id="{{ $card->id }}" data-state="idle" aria-label="{{ __st('catalog.card.add_to_cart', ['name' => $card->name]) }}">
                        <x-ui::icon name="cart-add" :size="24" />
                    </button>
                @endif
            </div>
        @endif
    </div>
</article>
@endif
