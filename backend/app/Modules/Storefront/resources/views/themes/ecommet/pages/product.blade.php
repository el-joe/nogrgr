{{--
    Ecommet product details (Figma 1:3380 desktop, m 1:3073): gallery (swipe, drag, zoom,
    thumbnails), title, sold count, best-selling + category tags, live price / compare /
    discount, flash countdown, variation pickers, quantity, add to cart (fly-to-cart),
    favourite, free-shipping popup (1:2646), delivery estimate popover / sheet (Storefront
    additions 137:111644 / m 137:111815), reviews summary + list + modal (135:111287,
    write 135:111423, m 135:111460), product details, "You might also like".
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductPageData $page */
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $logo = \App\Modules\Storefront\Support\StorefrontTheme::logoUrl();
    $images = $page->images;
    // Gallery slides: images and videos in order (#114); the video drives the floating popup.
    $gallery = $page->media !== [] ? $page->media : array_map(fn ($url) => ['type' => 'image', 'url' => $url, 'poster' => null], $images);
    $pipVideo = $page->firstVideo();
    $reviews = $page->reviews;
    $reviewsUrl = Route::has('store-api.v1.products.reviews.index') ? route('store-api.v1.products.reviews.index', ['storeProduct' => $page->view->id()]) : null;
    $favoriteUrl = Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null;
    $maxQty = $page->stock !== null ? max(1, min(10, $page->stock)) : 10;
    $selected = collect($page->variants)->firstWhere('id', $page->selectedVariantId);
    $peak = $page->deliveryDays !== [] ? max(array_column($page->deliveryDays, 'percentage')) : 0;
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
    $soldLabel = $page->soldCount >= 1000 ? __st('product.sold_k', ['count' => rtrim(rtrim(number_format($page->soldCount / 1000, 1), '0'), '.')]) : __st('product.sold', ['count' => $page->soldCount]);
@endphp
<x-sf::layout :seo="$seo" page="product" :mobile-title="null">
    <script type="application/json" id="sf-product">{!! json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="eco-container pb-28 pt-4 lg:pb-16 lg:pt-6" data-product-page data-product="{{ $page->view->id() }}">
        {{-- Breadcrumbs --}}
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-6 hidden text-base text-eco-gray lg:block">
            <ol class="flex flex-wrap items-center gap-1">
                <li class="inline-flex items-center gap-1"><a href="{{ route('storefront.home') }}" class="hover:text-eco-black hover:underline">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                @foreach ($page->breadcrumbs as $crumb)
                    <li class="inline-flex items-center gap-1"><a href="{{ route('storefront.category', ['slug' => $crumb->slug]) }}" class="hover:text-eco-black hover:underline">{{ $crumb->name }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                @endforeach
                <li class="max-w-[200px] truncate text-eco-black" aria-current="page">{{ $page->name }}</li>
            </ol>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[470px_minmax(0,1fr)] lg:gap-[52px]">
            {{-- Gallery + reviews + details (left column on desktop) --}}
            <div class="grid content-start gap-6 max-lg:contents">
                <div class="relative -mx-4 flex gap-5 max-lg:order-1 lg:mx-0" data-gallery @if ($pipVideo) data-video-pip="{{ json_encode(['family' => 'ecommet', 'src' => $pipVideo['url'], 'poster' => $pipVideo['poster'], 'close' => __st('product.close_video'), 'open' => __st('product.open_video')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}" @endif>
                    @if (count($gallery) > 1)
                        <div class="swiper hidden h-[276px] w-[81px] shrink-0 lg:block" data-gallery-thumbs='{"direction":"vertical","spaceBetween":10}'>
                            <div class="swiper-wrapper">
                                @foreach ($gallery as $index => $item)
                                    <div class="swiper-slide !h-[62px] cursor-pointer">
                                        @if ($item['type'] === 'video')<span @class(['eco-thumb', 'is-active' => $index === 0])><x-sf::gallery.video-thumb :item="$item" :width="81" :height="62" /></span>@else<span @class(['eco-thumb', 'is-active' => $index === 0])><img src="{{ $item['url'] }}" alt="" width="81" height="62" loading="lazy" decoding="async"></span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="relative min-w-0 flex-1">
                        <div class="swiper aspect-[375/250] bg-eco-img lg:aspect-[367/276]" data-gallery-main>
                            <div class="swiper-wrapper">
                                @forelse ($gallery as $index => $item)
                                    <div @if ($item['type'] === 'video') data-gallery-video-slide @endif class="swiper-slide grid place-items-center">
                                        @if ($item['type'] === 'video')
                                            <x-sf::gallery.video :item="$item" :index="$index" :name="$page->name" />
                                        @else
                                            <div class="swiper-zoom-container">
                                                <img src="{{ $item['url'] }}" alt="{{ $page->name }}{{ $index ? ' ('.($index + 1).')' : '' }}" width="734" height="552" class="size-full object-contain mix-blend-multiply" @if ($index === 0) fetchpriority="high" loading="eager" data-fly-image @else loading="lazy" @endif decoding="async">
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="swiper-slide grid place-items-center text-eco-semi"><x-ui::icon name="image" :size="64" data-fly-image /></div>
                                @endforelse
                            </div>
                        </div>
                        @if (count($gallery) > 1)
                            <button type="button" class="absolute start-4 top-1/2 z-10 hidden size-[30px] -translate-y-1/2 place-items-center rounded-full bg-white text-eco-black shadow lg:grid" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="18" class="rtl:-scale-x-100" /></button>
                            <button type="button" class="absolute end-4 top-1/2 z-10 hidden size-[30px] -translate-y-1/2 place-items-center rounded-full bg-white text-eco-black shadow lg:grid" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" /></button>
                            <span class="absolute bottom-3 end-6 z-10 rounded-full bg-eco-black/20 px-3 py-0.5 text-sm text-white lg:hidden" data-gallery-counter aria-hidden="true">1/{{ count($gallery) }}</span>
                        @endif
                    </div>
                </div>

                {{-- Reviews summary + first reviews --}}
                <section class="grid gap-4 border-eco-semi max-lg:order-4 max-lg:border-t-8 max-lg:border-[#f6f6f6] max-lg:pt-4" aria-labelledby="sf-reviews-title" data-reviews data-reviews-url="{{ $reviewsUrl }}">
                    <button type="button" class="flex items-center gap-2 border-b border-eco-semi pb-5 text-start lg:pb-7" data-sheet-open="sf-reviews" @disabled(! $reviewsUrl)>
                        <h2 id="sf-reviews-title" class="text-base text-eco-black lg:order-1">
                            <span class="lg:hidden">{{ number_format($reviews['average'], 1) }}</span>
                            <span class="hidden lg:inline">{{ __st('product.reviews.count', ['count' => $reviews['count']]) }}</span>
                        </h2>
                        <span class="hidden h-5 w-px bg-eco-semi lg:order-2 lg:block" aria-hidden="true"></span>
                        <span class="hidden text-base lg:order-3 lg:inline">{{ number_format($reviews['average'], 1) }}</span>
                        <x-sf::stars :rating="$reviews['average']" class="lg:order-4" />
                        <span class="text-sm text-eco-gray lg:hidden">({{ $reviews['count'] }})</span>
                        <x-ui::icon name="chevron-right" :size="20" class="ms-auto lg:hidden rtl:-scale-x-100" />
                    </button>
                    @if ($reviews['count'] > 0 && $reviewsUrl)
                        <div class="grid gap-6" data-reviews-preview aria-live="polite">
                            @for ($i = 0; $i < 2; $i++)
                                <div class="grid gap-2" aria-hidden="true"><span class="eco-skeleton h-6 w-40"></span><span class="eco-skeleton h-4 w-24"></span><span class="eco-skeleton h-12"></span></div>
                            @endfor
                        </div>
                        <button type="button" class="eco-pill-outline mx-auto h-[30px] border-eco-black px-4 text-sm" data-sheet-open="sf-reviews">{{ __st('product.reviews.see_all') }}</button>
                    @else
                        <p class="text-sm text-eco-gray">{{ __st('product.reviews.none') }}</p>
                    @endif
                </section>

                {{-- Product details --}}
                @if ($page->description || $page->summary)
                    <section class="grid gap-2 border-t border-eco-semi pt-6 max-lg:order-5 max-lg:border-t-8 max-lg:border-[#f6f6f6] max-lg:pt-4" aria-labelledby="sf-details-title">
                        <h2 id="sf-details-title" class="text-xl text-eco-black max-lg:border-b max-lg:border-eco-stroke max-lg:pb-2 lg:mb-2">{{ __st('product.details') }}</h2>
                        <div class="eco-prose eco-clamp" data-collapsed data-clamp id="sf-details-body">
                            @if ($page->description)
                                {!! \App\Modules\Notifications\Support\EmailHtmlSanitizer::clean($page->description) !!}
                            @else
                                <p>{{ $page->summary }}</p>
                            @endif
                        </div>
                        <button type="button" class="inline-flex w-fit items-center gap-1 text-sm text-eco-black" aria-expanded="false" aria-controls="sf-details-body" data-clamp-toggle data-less="{{ __st('product.details_less') }}">
                            <span data-clamp-label>{{ __st('product.details_more') }}</span><x-ui::icon name="chevron-down" :size="16" />
                        </button>
                    </section>
                @endif
            </div>

            {{-- Buy box --}}
            <div class="grid content-start gap-4 max-lg:order-2 lg:max-w-[480px]">
                <h1 class="text-base leading-[23px] tracking-[0.5px] text-eco-black max-lg:line-clamp-2 max-lg:text-sm" data-title>{{ $page->name }}</h1>
                <div class="flex flex-wrap items-center gap-2 text-xs tracking-[0.5px] text-eco-gray">
                    @if ($page->soldCount > 0)<span>{{ $soldLabel }}</span><span class="h-3 w-px bg-eco-semi" aria-hidden="true"></span>@endif
                    <span class="inline-flex items-center gap-1">{{ __st('product.sold_by') }}
                        <a href="{{ route('storefront.home') }}" class="inline-flex items-center gap-1 text-eco-black">
                            @if ($logo)<img src="{{ $logo }}" alt="{{ $store }}" class="h-4 w-auto max-w-[60px] object-contain" height="16">@else<span>{{ $store }}</span>@endif
                            <x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" />
                        </a>
                    </span>
                </div>
                @if ($page->bestSelling || $page->category)
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($page->bestSelling)<a href="{{ route('storefront.best-sellers') }}" class="eco-tag-red">{{ __st('catalog.card.best_selling_tag') }}</a>@endif
                        @if ($page->category)<a href="{{ route('storefront.category', ['slug' => $page->category->slug]) }}" class="inline-flex items-center gap-1 text-xs text-eco-gray underline">{{ __st('product.in_category', ['category' => $page->category->name]) }}<x-ui::icon name="chevron-right" :size="14" class="no-underline rtl:-scale-x-100" /></a>@endif
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3" aria-live="polite">
                    <span class="text-xl text-eco-gray line-through" data-compare @if (! $page->compare) hidden @endif><span class="sr-only">{{ __st('catalog.card.was') }}</span><span data-compare-value>{{ $page->compare }}</span></span>
                    <span class="text-2xl font-medium text-eco-black"><span class="sr-only">{{ __st('catalog.card.now') }}</span><span data-price>{{ $page->price }}</span></span>
                    <span class="eco-discount px-1.5 py-1 text-xs" data-discount @if (! $page->discount) hidden @endif>{{ __st('product.discount_off', ['percent' => $page->discount ?? 0]) }}</span>
                </div>

                @if ($page->flash && ($page->flash['ends_at'] ?? '') !== '')
                    <p class="inline-flex w-fit items-center gap-2 rounded-full bg-[rgb(255_215_208_/_0.59)] px-3 py-1.5 text-sm text-eco-red">
                        <x-ui::icon name="flame" :size="18" />{{ __st('product.flash', ['percent' => (int) $page->flash['percentage']]) }}
                        <span class="eco-countdown font-bold" data-countdown="{{ $page->flash['ends_at'] }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
                            <span data-unit="days">00</span>:<span data-unit="hours">00</span>:<span data-unit="minutes">00</span>:<span data-unit="seconds">00</span>
                        </span>
                    </p>
                @endif

                {{-- Variations --}}
                @foreach ($page->attributes as $attribute)
                    <fieldset class="grid gap-3" data-attribute="{{ $attribute['id'] }}">
                        <legend class="mb-3 text-base text-eco-black">{{ $attribute['name'] }}: <span class="text-eco-black max-lg:text-eco-gray" data-attribute-value>{{ collect($attribute['options'])->firstWhere('id', $selected['options'][$attribute['id']] ?? null)['name'] ?? '' }}</span></legend>
                        <div class="flex flex-wrap gap-3 max-lg:gap-2" role="radiogroup" aria-label="{{ $attribute['name'] }}">
                            @foreach ($attribute['options'] as $option)
                                @php($checked = ($selected['options'][$attribute['id']] ?? null) === $option['id'])
                                @if ($attribute['swatch'])
                                    <button type="button" role="radio" class="eco-swatch bg-[var(--swatch)] max-lg:size-4" data-swatch="{{ $option['swatch'] }}" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}" aria-label="{{ $option['name'] }}" title="{{ $option['name'] }}"></button>
                                @else
                                    <button type="button" role="radio" class="eco-option" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}">{{ $option['name'] }}</button>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                {{-- Quantity + add to cart --}}
                <div class="hidden items-center gap-4 lg:flex">
                    <label for="sf-qty" class="text-base text-eco-black">{{ __st('product.qty') }}</label>
                    <select id="sf-qty" class="h-[30px] w-[72px] rounded border border-eco-black bg-white px-2 text-base" data-qty>
                        @for ($q = 1; $q <= $maxQty; $q++)<option value="{{ $q }}">{{ $q }}</option>@endfor
                    </select>
                    <span class="text-base text-eco-black" data-added-label hidden>{{ __st('product.added') }}</span>
                </div>
                <div class="flex items-center gap-2 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-[76px] max-lg:z-30 max-lg:bg-white/95 max-lg:px-6 max-lg:py-2 max-lg:backdrop-blur lg:mt-6">
                    <button type="button" class="eco-pill-dark relative h-[38px] flex-1 font-medium disabled:cursor-not-allowed disabled:bg-eco-semi lg:max-w-[372px]" data-add-to-cart data-product-id="{{ $page->view->id() }}" data-variant-id="{{ $page->selectedVariantId }}" data-state="idle" @disabled(! $page->inStock)>
                        <span data-cart-label>{{ $page->inStock ? __st('product.add_to_cart') : __st('product.out_of_stock') }}</span>
                    </button>
                    @if ($favoriteUrl)
                        <button type="button" class="eco-heart shrink-0 max-lg:hidden" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="heart" :size="22" /></button>
                    @endif
                </div>
                @unless ($page->inStock)
                    <p class="rounded-lg bg-sf-danger-bg px-3 py-2 text-sm text-sf-danger" role="status">{{ __st('product.out_of_stock_note') }}</p>
                    @if (Route::has('store-api.v1.products.stock-alerts.store'))
                        @include('storefront::partials.stock-alert', ['productId' => $page->view->id(), 'buttonClass' => 'eco-pill-dark h-10 px-5 text-sm font-medium'])
                    @endif
                @endunless

                {{-- Shipping & delivery --}}
                <div class="mt-2 grid gap-3 border-eco-stroke max-lg:-mx-4 max-lg:border-y-8 max-lg:border-[#f6f6f6] max-lg:px-4 max-lg:py-4">
                    @if ($page->freeShippingGrams !== null)
                        <button type="button" class="inline-flex w-fit items-center gap-2 text-base text-eco-green" data-sheet-open="sf-free-shipping">
                            <x-ui::icon name="truck-tick" :size="24" />{{ __st('shell.topbar.free_shipping') }}<x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" />
                        </button>
                    @endif
                    <ul class="flex gap-4 overflow-x-auto text-sm text-eco-green [scrollbar-width:none]" role="list">
                        <li class="inline-flex shrink-0 items-center gap-1.5"><x-ui::icon name="check" :size="16" />{{ __st('product.promise_secure') }}</li>
                        <li class="inline-flex shrink-0 items-center gap-1.5"><x-ui::icon name="check" :size="16" />{{ __st('product.promise_guarantee') }}</li>
                        <li class="inline-flex shrink-0 items-center gap-1.5"><x-ui::icon name="check" :size="16" />{{ __st('product.promise_returns') }}</li>
                    </ul>
                    @if ($page->delivery)
                        <div class="relative text-sm text-eco-black" data-popover>
                            <p class="inline-flex flex-wrap items-center gap-1">{{ __st('product.delivery', ['range' => $page->delivery['label']]) }}
                                <button type="button" class="hidden size-5 place-items-center rounded-full text-eco-gray hover:text-eco-black lg:grid" aria-expanded="false" aria-controls="sf-delivery-pop" data-popover-toggle aria-label="{{ __st('product.delivery_details') }}"><x-ui::icon name="info" :size="16" /></button>
                                <button type="button" class="grid size-5 place-items-center rounded-full text-eco-gray lg:hidden" data-sheet-open="sf-delivery" aria-label="{{ __st('product.delivery_details') }}"><x-ui::icon name="info" :size="16" /></button>
                            </p>
                            @if ($page->delivery['company'])<p>{{ __st('product.courier', ['company' => $page->delivery['company']]) }}</p>@endif
                            <div id="sf-delivery-pop" class="eco-dropdown eco-dropdown-arrow start-0 top-[calc(100%+12px)] hidden w-[420px] p-5 [--arrow-x:40px] lg:block" role="dialog" aria-label="{{ __st('product.delivery_title') }}" data-popover-panel>
                                @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.delivery-estimate'), ['page' => $page, 'peak' => $peak])
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- You might also like --}}
        @if ($page->related !== [])
            <section class="mt-12 grid gap-6 lg:mt-16" aria-labelledby="sf-related-title">
                <h2 id="sf-related-title" class="text-center text-xl font-medium text-eco-black lg:text-2xl" data-reveal="fade-up">{{ __st('product.related') }}</h2>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4" data-reveal-stagger>
                    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $page->related])
                </div>
            </section>
        @endif
    </div>

    @if ($page->delivery)
        <x-sf::modal id="sf-delivery" :title="__st('product.delivery_title')" size="sm">
            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.delivery-estimate'), ['page' => $page, 'peak' => $peak])
        </x-sf::modal>
    @endif
    @if ($reviewsUrl)
        @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.reviews-modal'), ['page' => $page])
    @endif
</x-sf::layout>
