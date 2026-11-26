{{--
    Elora product card (Figma "Elora Variants" card 1:26741 / mobile card 1:26858).
      variant="card"  vertical: 210×183 image (badge tag top-end, heart top-start, cart button
                      bottom-end), name + weight, rating, price + struck compare + "% Off",
                      stock line.
      variant="wide"  horizontal (Trending / New In wide cards): image 192 at the start, the
                      text at the end with a sold-progress bar.
    Colours follow the variant tokens (--elo-price, --elo-weight, --elo-tag-bg). Same JS hooks
    as every card: [data-add-to-cart] (fly-to-cart), [data-favorite] (heart burst), the
    hover image cross-fade (mo-card / data-hover-image).
--}}
@props(['card', 'variant' => 'card', 'eager' => false, 'rank' => null])
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductCardData $card */
    $wide = $variant === 'wide';
    $favoriteUrl = Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null;
    $low = $card->lowStock();
    $sold = $card->flash !== null ? $card->flashProgress() : null;
    $tag = $card->badge ?? ($rank ? __st('catalog.card.rank', ['rank' => $rank]) : null);
@endphp
<article {{ $attributes->class(['elo-card mo-card', 'elo-card-wide' => $wide]) }} data-product="{{ $card->id }}">
    <div class="elo-card-media mo-card-media">
        @if ($card->image)
            <img src="{{ $card->image }}" alt="{{ $card->name }}" width="210" height="183" class="size-full object-cover" loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif decoding="async" data-fly-image>
            @if ($card->image2)
                <img src="{{ $card->image2 }}" alt="" width="210" height="183" loading="lazy" decoding="async" aria-hidden="true" data-hover-image>
            @endif
        @else
            <span class="grid size-full place-items-center text-sf-muted" data-fly-image><x-ui::icon name="image" :size="40" /></span>
        @endif
        @if ($wide && $sold !== null)
            <span class="elo-card-sold">{{ __st('elora.card.sold_percent', ['percent' => $sold]) }}</span>
        @elseif ($tag)
            <span class="elo-card-tag">{{ $tag }}</span>
        @endif
        @if ($favoriteUrl)
            <button type="button" class="elo-card-heart" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $card->id }}" aria-pressed="{{ $card->favorited ? 'true' : 'false' }}" aria-label="{{ __st('elora.card.favorite', ['name' => $card->name]) }}">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path class="mo-heart-fill" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M12.6 20.3a1.3 1.3 0 0 1-1.2 0C8.7 18.9 3 15.3 3 9.6A4.9 4.9 0 0 1 7.9 4.7c1.7 0 3.2.8 4.1 2.1a4.9 4.9 0 0 1 4.1-2.1A4.9 4.9 0 0 1 21 9.6c0 5.7-5.7 9.3-8.4 10.7Z"/></svg>
            </button>
        @endif
        @if ($card->hasVariants || ! $card->inStock)
            <a href="{{ $card->url }}" class="elo-card-cart mo-press" aria-label="{{ $card->inStock ? __st('catalog.card.choose_options', ['name' => $card->name]) : __st('catalog.card.view', ['name' => $card->name]) }}"><x-ui::icon name="cart-add" :size="24" /></a>
        @else
            <button type="button" class="elo-card-cart mo-press" data-add-to-cart data-product-id="{{ $card->id }}" data-state="idle" aria-label="{{ __st('catalog.card.add_to_cart', ['name' => $card->name]) }}">
                <span class="mo-state mo-state-idle"><x-ui::icon name="cart-add" :size="24" /></span>
                <span class="mo-state mo-state-loading"><x-ui::icon name="refresh" :size="20" class="mo-spin" /></span>
                <span class="mo-state mo-state-done"><x-ui::icon name="check" :size="22" /></span>
            </button>
        @endif
        @unless ($card->inStock)
            <span class="absolute inset-x-0 bottom-0 bg-black/70 py-1 text-center text-xs text-white">{{ __st('catalog.card.out_of_stock') }}</span>
        @endunless
    </div>
    <div class="elo-card-body">
        <div class="grid min-w-0 gap-1">
            <div class="flex min-w-0 items-center justify-between gap-2">
                <a href="{{ $card->url }}" class="elo-card-name" title="{{ $card->name }}">{{ $card->name }}</a>
                @if ($card->weight)<span class="elo-card-weight">{{ $card->weight }}</span>@endif
            </div>
        </div>
        @if ($wide && $sold !== null)
            <div class="grid gap-1">
                <span class="elo-card-bar" data-progress="{{ $sold }}" aria-hidden="true"><span class="w-[var(--p,0%)]"></span></span>
                @if ($card->soldCount > 0)<span class="text-xs text-elo-primary">{{ __st('elora.card.sold_count', ['count' => $card->soldCount]) }}</span>@endif
            </div>
        @endif
        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <x-sf::stars :rating="$card->rating" :size="12" />
                <span @class(['text-xs tracking-[0.5px] text-[#adadad]', 'max-lg:sr-only' => $card->reviewCount === 0])>{{ $card->reviewCount > 0 ? number_format($card->rating, 1).' (+'.$card->reviewCount.')' : __st('elora.card.no_reviews') }}</span>
            </div>
            <p class="flex flex-wrap items-end gap-x-2 whitespace-nowrap">
                <span class="elo-card-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span>{{ $card->price }}</span>
                @if ($card->compare)<span class="text-sm font-light text-[#adadad] line-through"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
                @if ($card->discount)<span class="text-xs text-[#ff3b30]">{{ __st('elora.card.off', ['percent' => $card->discount]) }}</span>@endif
            </p>
        </div>
        @if ($low)
            <p class="flex items-center gap-1.5 text-xs font-medium text-[#ff3b30]"><x-ui::icon name="cart" :size="16" />{{ __st('elora.card.only_left', ['count' => $low]) }}</p>
        @elseif ($card->starSeller)
            <p class="flex items-center gap-1.5 text-xs font-medium text-[#2aaf2f]"><x-ui::icon name="award" :size="16" />{{ __st('catalog.card.star_seller') }}</p>
        @endif
    </div>
</article>
