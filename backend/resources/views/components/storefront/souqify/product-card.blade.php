{{--
    Souqify product card (Figma "Souqify Variants": Deal Card 1:80148, SideProductCard 1:74511,
    wide card 1:76304, featured deal 1:72886).
      variant="card"  vertical: tall image (badge ribbon top-start, heart top-end, cart button
                      bottom-end, red "Only N left - Hurry up" / yellow star-seller strip),
                      name + weight, stars + rating, price + struck compare + "% off" chip.
      variant="wide"  horizontal (New In / Trending rows of v2–v6): image at the start with
                      heart, "N% Sold" chip and cart; name + weight, rating, price, % Off, an
                      optional sold bar ($progress), stock / star-seller line.
      variant="side"  deal side card (v1 flash / Explore Products): -N% chip, cart, name,
                      price + compare, stars, sold bar ("Sold: N" · "N left"), "Deal Ends in"
                      countdown boxes when the product is in a live flash sale. tone orange | accent.
      variant="deal"  featured deal (centre of the v1 flash sale): big image, HOT chip, name,
                      price, rating, sold bar and countdown.
    Same JS hooks as every storefront card: [data-add-to-cart] (fly-to-cart), [data-favorite]
    (heart burst), hover image cross-fade (mo-card / data-hover-image).
--}}
@props(['card', 'variant' => 'card', 'eager' => false, 'rank' => null, 'tone' => 'orange', 'progress' => false])
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductCardData $card */
    $favoriteUrl = Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null;
    $low = $card->lowStock();
    $sold = $card->flash !== null ? $card->flashProgress() : null;
    $tag = $card->badge ?? ($rank ? __st('catalog.card.rank', ['rank' => $rank]) : ($card->flash !== null ? __st('souqify.card.flash') : null));
    $chip = $card->discount === null ? null : ($card->discount >= 50 ? 'sq-chip-red' : ($card->discount >= 30 ? 'sq-chip-yellow' : 'sq-chip-orange'));
    $endsAt = $card->flash['ends_at'] ?? null;
    $soldBar = $sold ?? ($card->soldCount > 0 && $card->stockLeft !== null ? (int) round($card->soldCount / max(1, $card->soldCount + $card->stockLeft) * 100) : null);
@endphp
@if ($variant === 'side' || $variant === 'deal')
    @php $deal = $variant === 'deal'; @endphp
    <article {{ $attributes->class(['sq-side mo-card', 'sq-side-deal' => $deal, 'sq-side-accent' => $tone === 'accent']) }} data-product="{{ $card->id }}">
        <div class="sq-side-media mo-card-media">
            @if ($card->image)
                <img src="{{ $card->image }}" alt="{{ $card->name }}" width="{{ $deal ? 328 : 176 }}" height="{{ $deal ? 195 : 176 }}" class="size-full object-contain bg-media" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" data-fly-image>
                @if ($card->image2)
                    <img src="{{ $card->image2 }}" alt="" width="176" height="176" loading="lazy" decoding="async" aria-hidden="true" data-hover-image>
                @endif
            @else
                <span class="grid size-full place-items-center text-sf-muted" data-fly-image><x-ui::icon name="image" :size="36" /></span>
            @endif
            @if ($deal)
                <span class="sq-side-hot">{{ __st('souqify.card.hot') }}</span>
            @elseif ($card->discount)
                <span class="sq-side-off">-{{ $card->discount }}%</span>
            @endif
            @unless ($deal)
                @include('components.storefront.souqify.partials.card-cart', ['card' => $card, 'class' => 'sq-side-cart', 'size' => 24])
            @endunless
            @unless ($card->inStock)
                <span class="absolute inset-x-0 bottom-0 bg-black/70 py-1 text-center text-xs text-white">{{ __st('catalog.card.out_of_stock') }}</span>
            @endunless
        </div>
        <div class="sq-side-body">
            <a href="{{ $card->url }}" class="sq-side-name" title="{{ $card->name }}">{{ $card->name }}</a>
            <p class="flex flex-wrap items-baseline gap-x-1 whitespace-nowrap">
                <span class="sq-side-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span>{{ $card->price }}</span>
                @if ($card->compare)<span class="text-xs tracking-[0.5px] text-[#99a1af] line-through"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
            </p>
            <div class="flex items-center gap-2">
                <x-sf::stars :rating="$card->rating" :size="$deal ? 14 : 12" />
                @if ($card->reviewCount > 0)<span class="text-xs text-[#6a7282]">({{ $card->reviewCount }})</span>@endif
            </div>
            @if ($soldBar !== null || $low)
                <div class="grid gap-0.5">
                    <span class="sq-bar" data-progress="{{ $soldBar ?? 0 }}" aria-hidden="true"><span class="w-[var(--p,0%)]"></span></span>
                    <span class="flex items-center justify-between gap-2 text-xs tracking-[0.5px] text-[#6a7282]">
                        <span>@if ($card->soldCount > 0){{ __st('souqify.card.sold') }} <span class="sq-side-sold">{{ $card->soldCount }}</span>@endif</span>
                        @if ($low)<span class="text-[#de1709]">{{ __st('souqify.card.left', ['count' => $low]) }}</span>@endif
                    </span>
                </div>
            @endif
            @if ($endsAt)
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base font-medium text-[#242424] max-lg:sr-only">{{ __st('souqify.card.deal_ends') }}</span>
                    <p class="sq-side-clock" data-countdown="{{ $endsAt }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
                        <span><span data-unit="hours">00</span> :</span><span><span data-unit="minutes">00</span> :</span><span data-unit="seconds">00</span>
                    </p>
                </div>
            @endif
            @if ($deal)
                @include('components.storefront.souqify.partials.card-cart', ['card' => $card, 'class' => 'sq-side-cart', 'size' => 24])
            @endif
        </div>
    </article>
@else
    @php $wide = $variant === 'wide'; @endphp
    <article {{ $attributes->class(['sq-pc mo-card', 'sq-pc-wide' => $wide]) }} data-product="{{ $card->id }}">
        <div class="sq-pc-media mo-card-media">
            @if ($card->image)
                <img src="{{ $card->image }}" alt="{{ $card->name }}" width="{{ $wide ? 140 : 394 }}" height="{{ $wide ? 140 : 451 }}" class="size-full object-contain bg-media" loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif decoding="async" data-fly-image>
                @if ($card->image2)
                    <img src="{{ $card->image2 }}" alt="" width="{{ $wide ? 140 : 394 }}" height="{{ $wide ? 140 : 451 }}" loading="lazy" decoding="async" aria-hidden="true" data-hover-image>
                @endif
            @else
                <span class="grid size-full place-items-center text-sf-muted" data-fly-image><x-ui::icon name="image" :size="40" /></span>
            @endif
            @if ($wide)
                @if ($sold !== null)
                    <span class="sq-pc-sold">{{ __st('souqify.card.sold_percent', ['percent' => $sold]) }}</span>
                @elseif ($tag)
                    <span class="sq-pc-sold">{{ $tag }}</span>
                @endif
            @elseif ($tag)
                <span class="sq-pc-tag">{{ $tag }}</span>
            @endif
            @if ($favoriteUrl)
                <button type="button" class="sq-pc-heart" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $card->id }}" aria-pressed="{{ $card->favorited ? 'true' : 'false' }}" aria-label="{{ __st('souqify.card.favorite', ['name' => $card->name]) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path class="mo-heart-fill" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M12.6 20.3a1.3 1.3 0 0 1-1.2 0C8.7 18.9 3 15.3 3 9.6A4.9 4.9 0 0 1 7.9 4.7c1.7 0 3.2.8 4.1 2.1a4.9 4.9 0 0 1 4.1-2.1A4.9 4.9 0 0 1 21 9.6c0 5.7-5.7 9.3-8.4 10.7Z"/></svg>
                </button>
            @endif
            @include('components.storefront.souqify.partials.card-cart', ['card' => $card, 'class' => 'sq-pc-cart', 'size' => $wide ? 20 : 30])
            @if (! $wide)
                @if (! $card->inStock)
                    <span class="sq-pc-strip sq-pc-strip-dark">{{ __st('catalog.card.out_of_stock') }}</span>
                @elseif ($low)
                    <span class="sq-pc-strip sq-pc-strip-red"><x-ui::icon name="cart" :size="16" />{{ __st('souqify.card.hurry', ['count' => $low]) }}</span>
                @elseif ($card->starSeller)
                    <span class="sq-pc-strip sq-pc-strip-yellow"><x-ui::icon name="award" :size="16" />{{ __st('catalog.card.star_seller') }}</span>
                @endif
            @elseif (! $card->inStock)
                <span class="absolute inset-x-0 bottom-0 bg-black/70 py-0.5 text-center text-[10px] text-white">{{ __st('catalog.card.out_of_stock') }}</span>
            @endif
        </div>
        <div class="sq-pc-body">
            <div class="flex min-w-0 items-start justify-between gap-2">
                <a href="{{ $card->url }}" class="sq-pc-name" title="{{ $card->name }}">{{ $card->name }}</a>
                @if ($card->weight)<span class="sq-pc-weight">{{ $card->weight }}</span>@endif
            </div>
            @if ($wide && $progress && $soldBar !== null)
                <div class="grid gap-1">
                    <span class="sq-bar sq-bar-accent" data-progress="{{ $soldBar }}" aria-hidden="true"><span class="w-[var(--p,0%)]"></span></span>
                    @if ($card->soldCount > 0)<span class="text-xs text-sf-accent">{{ __st('souqify.card.sold_count', ['count' => $card->soldCount]) }}</span>@endif
                </div>
            @endif
            <div class="flex items-center gap-2">
                <x-sf::stars :rating="$card->rating" :size="$wide ? 12 : 16" />
                <span @class(['sq-pc-rating', 'max-lg:sr-only' => $card->reviewCount === 0])>{{ $card->reviewCount > 0 ? number_format($card->rating, 1).' (+'.$card->reviewCount.')' : __st('souqify.card.no_reviews') }}</span>
            </div>
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 whitespace-nowrap">
                <span class="sq-pc-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span>{{ $card->price }}</span>
                @if ($card->compare)<span class="sq-pc-compare"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
                @if ($card->discount)
                    @if ($wide)
                        <span class="text-xs text-[#ff3b30]">{{ __st('souqify.card.off_title', ['percent' => $card->discount]) }}</span>
                    @else
                        <span class="sq-chip {{ $chip }}">{{ __st('souqify.card.off', ['percent' => $card->discount]) }}</span>
                    @endif
                @endif
            </p>
            @if ($wide)
                @if ($low)
                    <p class="flex items-center gap-1.5 text-xs text-[#de1709]"><x-ui::icon name="cart" :size="14" />{{ __st('souqify.card.only_left', ['count' => $low]) }}</p>
                @elseif ($card->starSeller)
                    <p class="flex items-center gap-1.5 text-xs text-[#2aaf2f]"><x-ui::icon name="award" :size="14" />{{ __st('catalog.card.star_seller') }}</p>
                @endif
            @endif
        </div>
    </article>
@endif
