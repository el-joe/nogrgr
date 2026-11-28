{{--
    Elora v1 home product card (Figma 1:20600, phone 1:22761): image with the cart button at
    the bottom end, name + weight, summary, rating, price + struck compare + "% Off" and one
    stock / delivery line, and the product's badge ribbon at the top end. size: card (rails) | grid (phone two-column grid) | mini (phone
    "Recommended for You" band). The favourite heart appears on hover / focus only, so the
    resting card stays as designed. JS hooks: [data-add-to-cart], [data-favorite].
--}}
@props(['card', 'size' => 'card', 'delivery' => null, 'eager' => false])
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductCardData $card */
    $favoriteUrl = $size !== 'mini' && Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null;
    $low = $card->lowStock();
@endphp
<article {{ $attributes->class(['v1-card mo-card', 'v1-card-'.$size]) }} data-product="{{ $card->id }}">
    <div class="v1-card-media mo-card-media">
        @if ($card->image)
            <img src="{{ $card->image }}" alt="{{ $card->name }}" width="210" height="183" class="size-full object-contain" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" data-fly-image>
            @if ($card->image2 && $size !== 'mini')
                <img src="{{ $card->image2 }}" alt="" width="210" height="183" loading="lazy" decoding="async" aria-hidden="true" data-hover-image>
            @endif
        @else
            <span class="grid size-full place-items-center text-sf-muted" data-fly-image><x-ui::icon name="image" :size="40" /></span>
        @endif
        {{-- Corner badge = the product's badge record (Figma "sale" component 1:16840 / 1:16842). --}}
        @if ($card->badge)
            <span class="v1-card-badge" data-badge="{{ $card->badgeType }}" dir="auto" title="{{ $card->badge }}">{{ $card->badge }}</span>
        @endif
        @if ($favoriteUrl)
            <button type="button" class="v1-card-heart" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $card->id }}" aria-pressed="{{ $card->favorited ? 'true' : 'false' }}" aria-label="{{ __st('elora.card.favorite', ['name' => $card->name]) }}">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path class="mo-heart-fill" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M12.6 20.3a1.3 1.3 0 0 1-1.2 0C8.7 18.9 3 15.3 3 9.6A4.9 4.9 0 0 1 7.9 4.7c1.7 0 3.2.8 4.1 2.1a4.9 4.9 0 0 1 4.1-2.1A4.9 4.9 0 0 1 21 9.6c0 5.7-5.7 9.3-8.4 10.7Z"/></svg>
            </button>
        @endif
        @if ($card->hasVariants && $card->inStock)
            {{-- Variant products open the option picker (x-sf::variant-picker theme="elora", catalog/variant-picker.js); the link is the no-JS fallback. --}}
            <a href="{{ $card->url }}" class="v1-card-cart mo-press" data-variant-picker data-product-id="{{ $card->id }}" role="button" aria-haspopup="dialog" aria-label="{{ __st('catalog.card.choose_options', ['name' => $card->name]) }}"><span class="v1-ico v1-ico-card-cart" aria-hidden="true"></span></a>
        @elseif (! $card->inStock)
            <button type="button" class="v1-card-cart" disabled aria-disabled="true" title="{{ __st('catalog.card.out_of_stock') }}" aria-label="{{ __st('catalog.card.out_of_stock') }}: {{ $card->name }}"><span class="v1-ico v1-ico-card-cart" aria-hidden="true"></span></button>
        @else
            <button type="button" class="v1-card-cart mo-press" data-add-to-cart data-product-id="{{ $card->id }}" data-state="idle" aria-label="{{ __st('catalog.card.add_to_cart', ['name' => $card->name]) }}">
                <span class="mo-state mo-state-idle"><span class="v1-ico v1-ico-card-cart" aria-hidden="true"></span></span>
                <span class="mo-state mo-state-loading"><x-ui::icon name="refresh" :size="20" class="mo-spin" /></span>
                <span class="mo-state mo-state-done"><x-ui::icon name="check" :size="22" /></span>
            </button>
        @endif
        @unless ($card->inStock)
            <span class="v1-card-soldout">{{ __st('catalog.card.out_of_stock') }}</span>
        @endunless
    </div>
    <div class="v1-card-body">
        <div class="v1-card-head">
            <div class="v1-card-row">
                <a href="{{ $card->url }}" class="v1-card-name" dir="auto" title="{{ $card->name }}">{{ $card->name }}</a>
                @if ($card->weight)<span class="v1-card-weight" dir="ltr">{{ $card->weight }}</span>@endif
            </div>
            <p class="v1-card-summary" dir="auto">{{ $card->summary }}</p>
        </div>
        <div class="v1-card-meta">
            <div class="v1-card-rating">
                <x-sf::stars :rating="$card->rating" :size="12" class="v1-stars" />
                <span>{{ $card->reviewCount > 0 ? number_format($card->rating, 1).' (+'.$card->reviewCount.')' : __st('elora.card.no_reviews') }}</span>
            </div>
            {{-- Long currency strings (e.g. "EGP 1,234.00") use tighter type so nothing is cut. --}}
            <p @class(['v1-card-prices', 'v1-card-prices-long' => mb_strlen($card->price) > 7])>
                <span class="v1-card-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span>{{ $card->price }}</span>
                @if ($card->compare)<span class="v1-card-compare"><span class="sr-only">{{ __st('catalog.card.was') }}</span>{{ $card->compare }}</span>@endif
                @if ($card->discount)<span class="v1-card-off">{{ __st('elora.card.off', ['percent' => $card->discount]) }}</span>@endif
            </p>
        </div>
        <p class="v1-card-line">
            @if ($low)
                <span class="v1-card-low"><span class="v1-ico v1-ico-cart-low" aria-hidden="true"></span>{{ __st('elora.card.only_left', ['count' => $low]) }}</span>
            @elseif ($card->inStock && $delivery)
                <span class="v1-card-delivery"><span class="v1-ico v1-ico-delivery" aria-hidden="true"></span>{{ __st('elora.card.delivered_by', ['date' => $delivery]) }}</span>
            @endif
        </p>
    </div>
</article>
