{{--
    Elora v1 (base) product details, only variant 1 (variants 2-6 keep pages/product) — Figma
    1:16245 (desktop 1440), compact variant without reviews 1:16088, phone 1:21580 (375).
    Desktop: free-shipping bar, breadcrumb, gallery 694 x 395 with a 7-up thumbnail strip (a
    "Play Video" thumb opens the video slide, the floating popup follows the page), product
    details + reviews under it; right column title, rating, price, delivery, colour / size
    chips, qty, add to cart + heart + share and the 2 x 2 trust cards; three rails below.
    Phones: back / search / heart / share header, gallery with badges, buy info, free-shipping
    band, stacked trust cards, details, reviews, rails and the sticky quantity + add-to-cart bar
    (no tab bar on this page, like the design). Images and thumbnails fill their boxes with
    object-fit: contain. Styles: resources/css/storefront/elora-pdp.css.
    Data as the Ecommet product page: $page (ProductPageData), $seo.
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductPageData $page */
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $images = $page->images;
    // Gallery slides: images and videos in order (#114); the video drives the floating popup.
    $gallery = $page->media !== [] ? $page->media : array_map(fn ($url) => ['type' => 'image', 'url' => $url, 'poster' => null], $images);
    $pipVideo = $page->firstVideo();
    $reviews = $page->reviews;
    $compact = $reviews['count'] === 0;
    $reviewsUrl = Route::has('store-api.v1.products.reviews.index') ? route('store-api.v1.products.reviews.index', ['storeProduct' => $page->view->id()]) : null;
    $favoriteUrl = Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null;
    $maxQty = $page->stock !== null ? max(1, min(10, $page->stock)) : 10;
    $selected = collect($page->variants)->firstWhere('id', $page->selectedVariantId);
    $peak = $page->deliveryDays !== [] ? max(array_column($page->deliveryDays, 'percentage')) : 0;
    $fastest = collect($page->deliveryDays)->first(fn ($row) => $row['percentage'] > 0);
    $island = [
        'id' => $page->view->id(),
        'variants' => $page->variants,
        'attributes' => array_map(fn ($attribute) => ['id' => $attribute['id'], 'name' => $attribute['name']], $page->attributes),
        'selected' => $page->selectedVariantId,
        'reviews_url' => $reviewsUrl,
        'texts' => [
            'out_of_stock' => __st('product.out_of_stock'),
            'add_to_cart' => __st('product.add_to_cart'),
            'unavailable' => __st('product.unavailable'),
        ],
    ];
    $grams = $page->freeShippingGrams;
    $weight = $grams === null ? null : ($grams >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($grams / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $grams]));
    // A threshold of 0 g is free shipping from the first gram, not "orders of 0 g".
    $freeText = $grams === 0 ? __st('shell.free_shipping.title') : __st('elora_pages.product.free_shipping_over', ['weight' => $weight]);
    $detailsText = trim(strip_tags((string) ($page->description ?: $page->summary)));
    $moreUrl = $page->category ? route('storefront.category', ['slug' => $page->category->slug]) : route('storefront.best-sellers');
@endphp
<x-sf::layout :seo="$seo" page="product">
    <script type="application/json" id="sf-product">{!! json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="epd" data-product-page data-product="{{ $page->view->id() }}">
        <div class="epd-wrap">
            {{-- Phone bar: back, search, favourite, share (m 1:22005) --}}
            <div class="epd-mbar lg:hidden">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('storefront.home') }}" class="epd-circle" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="24" class="rtl:-scale-x-100" /></a>
                <form method="GET" action="{{ route('storefront.search') }}" role="search" class="epd-msearch">
                    <x-ui::icon name="search" :size="20" />
                    <label for="epd-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
                    <input id="epd-search" type="search" name="q" placeholder="{{ __st('shell.search') }}" enterkeyhint="search">
                </form>
                @if ($favoriteUrl)
                    <button type="button" class="epd-circle" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="elo-heart" :size="20" /></button>
                @endif
                <button type="button" class="epd-circle" data-elo-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('elora_pages.product.link_copied') }}" aria-label="{{ __st('elora_pages.product.share') }}"><x-ui::icon name="elo-share" :size="24" /></button>
            </div>

            @if ($weight)
                <button type="button" class="epd-freebar max-lg:hidden" data-sheet-open="sf-free-shipping-elo" data-reveal="fade">{{ $freeText }}</button>
            @endif

            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="epd-crumbs max-lg:hidden">
                <ol>
                    <li><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                    @foreach ($page->breadcrumbs as $crumb)
                        <li><a href="{{ route('storefront.category', ['slug' => $crumb->slug]) }}">{{ $crumb->name }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                    @endforeach
                    <li class="epd-crumb-now" aria-current="page">{{ $page->name }}</li>
                </ol>
            </nav>

            <div class="epd-grid">
                {{-- Gallery + details + reviews --}}
                <div class="epd-main">
                    <div class="epd-gallery" data-gallery @if ($pipVideo) data-video-pip="{{ json_encode(['family' => 'elora', 'src' => $pipVideo['url'], 'poster' => $pipVideo['poster'], 'close' => __st('product.close_video'), 'open' => __st('product.open_video')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}" @endif>
                        <div class="epd-stage">
                            <div class="swiper epd-main-swiper" data-gallery-main='{"autoHeight":true}'>
                                <div class="swiper-wrapper">
                                    @forelse ($gallery as $index => $item)
                                        <div @if ($item['type'] === 'video') data-gallery-video-slide @endif class="swiper-slide">
                                            @if ($item['type'] === 'video')
                                                <x-sf::gallery.video :item="$item" :index="$index" :name="$page->name" />
                                            @else
                                                <div class="swiper-zoom-container">
                                                    <img src="{{ $item['url'] }}" alt="{{ $page->name }}{{ $index ? ' ('.($index + 1).')' : '' }}" width="694" height="395" @if ($index === 0) fetchpriority="high" loading="eager" data-fly-image @else loading="lazy" @endif decoding="async">
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="swiper-slide grid place-items-center text-elo-line"><x-ui::icon name="image" :size="64" data-fly-image /></div>
                                    @endforelse
                                </div>
                            </div>
                            @if ($page->bestSelling)
                                <a href="{{ route('storefront.best-sellers') }}" class="epd-badge">{{ __st('catalog.card.best_selling_tag') }}</a>
                            @endif
                            @if ($gallery !== [])
                                <button type="button" class="epd-zoom" data-gallery-zoom aria-label="{{ __st('elora_pages.product.zoom') }}"><span class="epd-ico epd-ico-zoom" aria-hidden="true"></span></button>
                            @endif
                            @if (count($gallery) > 1)
                                <button type="button" class="epd-arrow epd-arrow-prev max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><span class="epd-ico epd-ico-chevron" aria-hidden="true"></span></button>
                                <button type="button" class="epd-arrow epd-arrow-next max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><span class="epd-ico epd-ico-chevron" aria-hidden="true"></span></button>
                                <span class="epd-counter" data-gallery-counter aria-hidden="true">1/{{ count($gallery) }}</span>
                            @endif
                            @if ($weight)
                                <div class="epd-promo lg:hidden" aria-hidden="true">
                                    <span><x-ui::icon name="box" :size="16" />{{ __st('shell.free_shipping.title') }}</span>
                                    <span><x-ui::icon name="check-circle" :size="16" />{{ __st('cart.trust.guarantee.credit') }}</span>
                                </div>
                            @endif
                        </div>
                        @if (count($gallery) > 1)
                            <div class="swiper epd-thumbs" data-gallery-thumbs='{"slidesPerView":7,"spaceBetween":7,"breakpoints":{"1024":{"slidesPerView":7,"spaceBetween":13}}}'>
                                <div class="swiper-wrapper">
                                    @foreach ($gallery as $index => $item)
                                        <div class="swiper-slide @if ($index === 0) is-active @endif">
                                            <span class="epd-thumb">
                                                @if ($item['type'] === 'video')
                                                    <x-sf::gallery.video-thumb :item="$item" :width="88" :height="80" :label="true" />
                                                @else
                                                    <img src="{{ $item['url'] }}" alt="" width="88" height="80" loading="lazy" decoding="async">
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if (! $compact && $detailsText !== '')
                        <section class="epd-details" aria-labelledby="epd-details-title" data-reveal="fade-up">
                            <h2 id="epd-details-title">{{ __st('elora_pages.product.details') }}</h2>
                            <div class="elo-prose epd-prose" data-collapsed data-clamp id="epd-details-body">
                                @if ($page->description)
                                    {!! \App\Modules\Notifications\Support\EmailHtmlSanitizer::clean($page->description) !!}
                                @else
                                    <p>{{ $page->summary }}</p>
                                @endif
                            </div>
                            <button type="button" class="epd-more" aria-expanded="false" aria-controls="epd-details-body" data-clamp-toggle data-less="{{ __st('product.details_less') }}">
                                <span data-clamp-label>{{ __st('elora_pages.product.see_more') }}</span><x-ui::icon name="chevron-down" :size="16" />
                            </button>
                        </section>
                    @endif

                    @unless ($compact)
                        <section class="epd-reviews" aria-labelledby="epd-reviews-title" data-reviews data-reviews-url="{{ $reviewsUrl }}">
                            <h2 id="epd-reviews-title">{{ __st('elora_pages.product.reviews_rating') }}</h2>
                            <div class="epd-rev-head">
                                <p class="epd-verified"><x-ui::icon name="elo-user-check" :size="20" />{{ __st('elora_pages.product.verified_note') }}</p>
                                <div class="epd-rev-score">
                                    <p class="epd-rev-count">{{ __st('product.reviews.count', ['count' => $reviews['count']]) }}</p>
                                    <p class="epd-rev-avg"><span>{{ number_format($reviews['average'], 1) }}</span><x-sf::stars :rating="$reviews['average']" :size="32" tone="dark" figma /></p>
                                </div>
                            </div>
                            @if ($reviewsUrl)
                                <div class="epd-rev-list" data-reviews-preview data-preview-count="3" aria-live="polite">
                                    @for ($i = 0; $i < 3; $i++)
                                        <div class="grid gap-2 py-4" aria-hidden="true"><span class="eco-skeleton h-5 w-40"></span><span class="eco-skeleton h-4 w-24"></span><span class="eco-skeleton h-5"></span></div>
                                    @endfor
                                </div>
                                <button type="button" class="epd-all-reviews mo-press" data-sheet-open="sf-reviews">{{ __st('elora_pages.product.see_all_reviews') }}</button>
                            @endif
                        </section>
                    @endunless
                </div>

                {{-- Buy box --}}
                <div class="epd-buy">
                    <div class="epd-titlebox">
                        <div class="epd-titlerow">
                            @if ($fastest && $page->delivery)
                                <button type="button" class="epd-fast lg:hidden" data-sheet-open="elo-delivery"><x-ui::icon name="truck" :size="18" />{{ __st('elora_pages.product.fastest', ['days' => $fastest['day']]) }}</button>
                            @endif
                            <h1 data-title>{{ $page->name }}</h1>
                        </div>
                        <div class="epd-ratingrow">
                            <button type="button" class="epd-rating" @if ($reviewsUrl && $reviews['count'] > 0) data-sheet-open="sf-reviews" @else disabled @endif>
                                <x-ui::icon name="elo-star" :size="18" class="text-[#ffe100]" /><span>{{ number_format($reviews['average'], 1) }}</span>
                                <span class="epd-rates">{{ __st('elora_pages.product.rates', ['count' => $reviews['count']]) }}</span>
                            </button>
                            @if ($page->soldCount > 0)
                                <span class="epd-sold">{{ $page->soldCount >= 1000 ? __st('product.sold_k', ['count' => rtrim(rtrim(number_format($page->soldCount / 1000, 1), '0'), '.')]) : __st('product.sold', ['count' => $page->soldCount]) }}</span>
                            @endif
                            @if ($page->flash && ($page->flash['ends_at'] ?? '') !== '')
                                <span class="epd-flash">
                                    <x-ui::icon name="clock" :size="18" />{{ __st('elora_pages.product.flash_ends') }}
                                    <span class="tabular-nums" data-countdown="{{ $page->flash['ends_at'] }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}"><span data-unit="days">00</span>:<span data-unit="hours">00</span>:<span data-unit="minutes">00</span>:<span data-unit="seconds">00</span></span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="epd-pricerow" aria-live="polite">
                        <span class="epd-compare" data-compare @if (! $page->compare) hidden @endif><span class="sr-only">{{ __st('catalog.card.was') }}</span><span data-compare-value>{{ $page->compare }}</span></span>
                        <span class="epd-price"><span class="sr-only">{{ __st('catalog.card.now') }}</span><span data-price>{{ $page->price }}</span></span>
                        <span class="epd-off" data-discount @if (! $page->discount) hidden @endif>{{ __st('product.discount_off', ['percent' => $page->discount ?? 0]) }}</span>
                    </div>

                    @if ($page->delivery)
                        <button type="button" class="epd-est" data-sheet-open="elo-delivery">
                            <x-ui::icon name="elo-truck-tick" :size="24" /><span>{{ __st('elora_pages.product.estimated', ['range' => $page->delivery['label']]) }}</span>
                        </button>
                    @endif

                    @if ($compact && ($page->summary || $page->description))
                        <p class="epd-summary">{{ $page->summary ?: \Illuminate\Support\Str::limit(strip_tags((string) $page->description), 220) }}</p>
                    @endif

                    @foreach ($page->attributes as $attribute)
                        <fieldset class="epd-attr" data-attribute="{{ $attribute['id'] }}">
                            <legend><span>{{ $attribute['name'] }}:</span> <span class="epd-attr-value" data-attribute-value>{{ collect($attribute['options'])->firstWhere('id', $selected['options'][$attribute['id']] ?? null)['name'] ?? '' }}</span></legend>
                            <div class="epd-chips" role="radiogroup" aria-label="{{ $attribute['name'] }}">
                                @foreach ($attribute['options'] as $option)
                                    @php($checked = ($selected['options'][$attribute['id']] ?? null) === $option['id'])
                                    <button type="button" role="radio" class="epd-chip" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}" title="{{ $option['name'] }}"><span class="epd-chip-text">{{ $option['name'] }}</span></button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="epd-qty max-lg:hidden">
                        <label for="elo-qty">{{ __st('product.qty') }}</label>
                        <select id="elo-qty" data-qty data-elo-qty-source>
                            @for ($q = 1; $q <= $maxQty; $q++)<option value="{{ $q }}">{{ $q }}</option>@endfor
                        </select>
                        <span data-added-label hidden>{{ __st('product.added') }}</span>
                    </div>

                    <div class="epd-buybar">
                        <div class="epd-stepper lg:hidden" data-elo-qty-mirror data-max="{{ $maxQty }}">
                            <button type="button" data-step="-1" aria-label="{{ __st('elora_pages.cart.decrease') }}" disabled><x-ui::icon name="minus" :size="24" /></button>
                            <input type="number" value="1" min="1" max="{{ $maxQty }}" readonly tabindex="-1" aria-label="{{ __st('product.qty') }}">
                            <button type="button" data-step="1" aria-label="{{ __st('elora_pages.cart.increase') }}" @disabled($maxQty <= 1)><x-ui::icon name="plus" :size="24" /></button>
                        </div>
                        <button type="button" class="epd-add" data-add-to-cart data-product-id="{{ $page->view->id() }}" data-variant-id="{{ $page->selectedVariantId }}" data-state="idle" @disabled(! $page->inStock)>
                            <x-ui::icon name="elo-cart-add" :size="20" /><span data-cart-label>{{ $page->inStock ? __st('product.add_to_cart') : __st('product.out_of_stock') }}</span>
                        </button>
                        @if ($favoriteUrl)
                            <button type="button" class="epd-circle epd-circle-shadow max-lg:hidden" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="elo-heart" :size="20" /></button>
                        @endif
                        <button type="button" class="epd-circle epd-circle-shadow max-lg:hidden" data-elo-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('elora_pages.product.link_copied') }}" aria-label="{{ __st('elora_pages.product.share') }}"><x-ui::icon name="elo-share" :size="24" /></button>
                    </div>
                    @unless ($page->inStock)
                        <p class="rounded-lg bg-sf-danger-bg px-3 py-2 text-sm text-sf-danger" role="status">{{ __st('product.out_of_stock_note') }}</p>
                        @if (Route::has('store-api.v1.products.stock-alerts.store'))
                            @include('storefront::partials.stock-alert', ['productId' => $page->view->id(), 'buttonClass' => 'elo-btn h-10 bg-elo-ink px-5 text-sm text-white hover:bg-black'])
                        @endif
                    @endunless

                    @if ($weight)
                        <button type="button" class="epd-freeband lg:hidden" data-sheet-open="sf-free-shipping-elo"><x-ui::icon name="box" :size="32" />{{ $freeText }}</button>
                    @endif

                    <x-sf::elora.inner.product-trust class="epd-trust" :images="true" />
                </div>
            </div>
        </div>

        <div class="epd-rails v1-home">
            <x-sf::elora.v1.pdp-rail :cards="$page->related" :title="__st('elora_pages.product.similar')" :more="$moreUrl" />
            <x-sf::elora.v1.pdp-rail :cards="$page->alsoViewed" :title="__st('elora_pages.product.also_viewed')" :more="route('storefront.best-sellers')" />
            <x-sf::elora.v1.pdp-rail :cards="$page->recommended" :title="__st('elora_pages.product.recommended')" :more="route('storefront.top-rated')" />
        </div>
    </div>

    @if ($page->delivery)
        @include('storefront::themes.elora.partials.delivery-estimate', ['page' => $page, 'peak' => $peak])
    @endif
    @include('storefront::themes.elora.partials.privacy-popup')
    @if ($weight)
        <x-sf::modal id="sf-free-shipping-elo" :title="__st('shell.free_shipping.title')" size="sm">
            <ul class="grid gap-3 text-sm text-elo-ink" role="list">
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ $freeText }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ __st('shell.free_shipping.line_2') }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ __st('shell.free_shipping.line_3') }}</li>
            </ul>
        </x-sf::modal>
    @endif
    @if ($reviewsUrl)
        @include('storefront::themes.elora.partials.reviews-modal', ['page' => $page])
    @endif
</x-sf::layout>
