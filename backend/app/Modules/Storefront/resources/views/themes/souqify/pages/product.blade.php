{{--
    Souqify product details (Figma 1:74583 / m 1:77427): gallery with a vertical thumbnail
    column on desktop (horizontal strip on phones; swipe, mouse drag, pinch / double-tap zoom,
    counter), orange tag, 48px title, rating, big accent price + struck price + "Save" pill,
    flash line with countdown, stock row (in stock · only N left), summary with "See more",
    free-shipping box, variation chips, the 68px "Add to cart – price" button + quantity
    stepper (fixed bottom bar on phones), delivery estimate (→ popup 137:111894), Description /
    Specifications tabs with the yellow guarantee card, "User Reviews" summary + preview (→
    modal 135:112511 / write 135:112647), "Complete the Collection" rail with arrows and
    "Recommended For You".
    Data as the Ecommet product page: $page (ProductPageData), $seo.
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductPageData $page */
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
    $grams = $page->freeShippingGrams;
    $weight = $grams === null ? null : ($grams >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($grams / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $grams]));
    $moreUrl = $page->category ? route('storefront.category', ['slug' => $page->category->slug]) : route('storefront.best-sellers');
    $lowStock = $page->inStock && $page->stock !== null && $page->stock <= 10;
    $specs = array_values(array_filter(array_map(fn ($attribute) => ['name' => $attribute['name'], 'values' => implode(', ', array_column($attribute['options'], 'name'))], $page->attributes), fn ($row) => $row['values'] !== ''));
    if ($page->category) {
        array_unshift($specs, ['name' => __st('souqify_pages.product.category'), 'values' => $page->category->name]);
    }
    $hasDetails = $page->description || $page->summary;
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges();
    $returns = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Returns->value]) : null;
    $recommended = [];

    try {
        $entries = app(\App\Modules\Storefront\Services\Catalog\ProductListing::class)->rail(\App\Modules\Storefront\Enums\ListingKind::BestSelling, 11);
        $recommended = array_slice(array_values(array_filter(
            app(\App\Modules\Storefront\Services\Catalog\ProductCards::class)->make($entries),
            fn ($card) => $card->id !== $page->view->id(),
        )), 0, 10);
    } catch (\Throwable) {
        $recommended = [];
    }
@endphp
<x-sf::layout :seo="$seo" page="product">
    <script type="application/json" id="sf-product">{!! json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="sq-wrap pb-36 pt-3 lg:pb-16 lg:pt-8" data-product-page data-product="{{ $page->view->id() }}">
        {{-- Phone bar: back, search, favourite, share (m 1:77427) --}}
        <div class="mb-3 flex items-center gap-2.5 lg:hidden">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('storefront.home') }}" class="sq-icon-btn" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></a>
            <form method="GET" action="{{ route('storefront.search') }}" role="search" class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-full border border-sq-line bg-white px-3">
                <x-ui::icon name="search" :size="16" class="text-sf-muted" />
                <label for="sq-p-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
                <input id="sq-p-search" type="search" name="q" placeholder="{{ __st('shell.search') }}" class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none" enterkeyhint="search">
            </form>
            @if ($favoriteUrl)
                <button type="button" class="sq-icon-btn" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="heart" :size="20" /></button>
            @endif
            <button type="button" class="sq-icon-btn" data-sq-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('souqify_pages.product.link_copied') }}" aria-label="{{ __st('souqify_pages.product.share') }}"><x-ui::icon name="upload" :size="18" /></button>
        </div>

        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-5 hidden lg:block">
            <ol class="sq-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                @foreach ($page->breadcrumbs as $crumb)
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.category', ['slug' => $crumb->slug]) }}">{{ $crumb->name }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                @endforeach
                <li class="max-w-[240px] truncate" aria-current="page">{{ $page->name }}</li>
            </ol>
        </nav>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,699px)_minmax(0,639px)] lg:justify-between lg:gap-8">
            {{-- Gallery (1:74585): thumbnails column + main image --}}
            <div class="sq-gallery grid gap-3 lg:grid-cols-[95px_minmax(0,1fr)] lg:gap-8" data-gallery @if ($pipVideo) data-video-pip="{{ json_encode(['family' => 'souqify', 'src' => $pipVideo['url'], 'poster' => $pipVideo['poster'], 'close' => __st('product.close_video'), 'open' => __st('product.open_video')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}" @endif data-reveal="fade">
                <div class="relative min-w-0 lg:order-2">
                    <div class="swiper sq-gallery-main aspect-square lg:aspect-[572/695]" data-gallery-main>
                        <div class="swiper-wrapper">
                            @forelse ($gallery as $index => $item)
                                <div @if ($item['type'] === 'video') data-gallery-video-slide @endif class="swiper-slide">
                                    @if ($item['type'] === 'video')
                                        <x-sf::gallery.video :item="$item" :index="$index" :name="$page->name" />
                                    @else
                                        <div class="swiper-zoom-container">
                                            <img src="{{ $item['url'] }}" alt="{{ $page->name }}{{ $index ? ' ('.($index + 1).')' : '' }}" width="1144" height="1390" class="size-full object-contain bg-media" @if ($index === 0) fetchpriority="high" loading="eager" data-fly-image @else loading="lazy" @endif decoding="async">
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="swiper-slide grid place-items-center text-sq-line"><x-ui::icon name="image" :size="64" data-fly-image /></div>
                            @endforelse
                        </div>
                    </div>
                    @if (count($gallery) > 1)
                        <button type="button" class="sq-icon-btn absolute start-3 top-1/2 z-10 hidden -translate-y-1/2 lg:grid" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="18" class="rtl:-scale-x-100" /></button>
                        <button type="button" class="sq-icon-btn absolute end-3 top-1/2 z-10 hidden -translate-y-1/2 lg:grid" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" /></button>
                        <span class="absolute bottom-3 end-3 z-10 rounded-full bg-black/60 px-2.5 py-0.5 text-xs text-white" data-gallery-counter aria-hidden="true">1/{{ count($gallery) }}</span>
                    @endif
                </div>
                @if (count($gallery) > 1)
                    <div class="swiper sq-thumbs w-full lg:order-1" data-gallery-thumbs='{"spaceBetween":10,"breakpoints":{"1024":{"direction":"vertical","spaceBetween":24}}}'>
                        <div class="swiper-wrapper">
                            @foreach ($gallery as $index => $item)
                                <div class="swiper-slide !h-auto !w-[64px] cursor-pointer lg:!h-[96px] lg:!w-[95px] @if ($index === 0) is-active @endif">
                                    @if ($item['type'] === 'video')<span class="sq-thumb"><x-sf::gallery.video-thumb :item="$item" :width="190" :height="192" /></span>@else<span class="sq-thumb"><img src="{{ $item['url'] }}" alt="" width="190" height="192" loading="lazy" decoding="async"></span>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Product info (1:74614) --}}
            <div class="grid content-start gap-4 lg:gap-6">
                <div class="grid gap-2">
                    @if ($page->bestSelling)
                        <a href="{{ route('storefront.best-sellers') }}" class="sq-label w-fit">{{ __st('catalog.card.best_selling_tag') }}</a>
                    @elseif ($page->flash)
                        <span class="sq-label w-fit">{{ __st('souqify_pages.product.flash_tag') }}</span>
                    @endif
                    <h1 class="text-2xl font-semibold leading-tight text-[#191b23] lg:text-5xl" data-title>{{ $page->name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <button type="button" class="inline-flex items-center gap-2 font-medium tracking-[0.5px] text-[#434655]" @if ($reviewsUrl && $reviews['count'] > 0) data-sheet-open="sf-reviews" @else disabled @endif>
                            <x-sf::stars :rating="$reviews['average']" :size="12" class="!text-sq-orange" />
                            {{ __st('souqify_pages.product.reviews_count', ['count' => $reviews['count']]) }}
                        </button>
                        @if ($page->soldCount > 0)
                            <span class="text-sf-muted">· {{ $page->soldCount >= 1000 ? __st('product.sold_k', ['count' => rtrim(rtrim(number_format($page->soldCount / 1000, 1), '0'), '.')]) : __st('product.sold', ['count' => $page->soldCount]) }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-2">
                    <div class="flex flex-wrap items-end gap-2" aria-live="polite">
                        <span class="text-[32px] font-bold leading-none text-sq-price lg:text-5xl"><span class="sr-only">{{ __st('catalog.card.now') }}</span><span data-price>{{ $page->price }}</span></span>
                        <span class="text-base leading-7 text-[#99a1af] line-through lg:text-xl" data-compare @if (! $page->compare) hidden @endif><span class="sr-only">{{ __st('catalog.card.was') }}</span><span data-compare-value>{{ $page->compare }}</span></span>
                        <span class="sq-save" data-discount @if (! $page->discount) hidden @endif>{{ __st('product.discount_off', ['percent' => $page->discount ?? 0]) }}</span>
                    </div>
                    @if ($page->flash && ($page->flash['ends_at'] ?? '') !== '')
                        <p class="flex flex-wrap items-center gap-2 text-xs font-semibold text-[#f54900]">
                            <x-ui::icon name="zap" :size="14" />{{ __st('souqify_pages.product.flash_line') }}
                            <span class="rounded-full bg-[#fff1e6] px-2 py-0.5 tabular-nums" data-countdown="{{ $page->flash['ends_at'] }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}"><span data-unit="days">00</span>:<span data-unit="hours">00</span>:<span data-unit="minutes">00</span>:<span data-unit="seconds">00</span></span>
                        </p>
                    @endif
                </div>

                <div class="sq-stock">
                    @if ($page->inStock)
                        <span class="sq-stock-cell text-sq-green"><span class="size-2.5 rounded-full bg-[#00c950] opacity-70" aria-hidden="true"></span>{{ __st('souqify_pages.product.in_stock') }}</span>
                    @else
                        <span class="sq-stock-cell text-sf-danger"><span class="size-2.5 rounded-full bg-sf-danger" aria-hidden="true"></span>{{ __st('product.out_of_stock') }}</span>
                    @endif
                    @if ($lowStock)
                        <span class="sq-stock-cell text-[#d8000a]"><x-ui::icon name="flame" :size="18" />{{ __st('souqify_pages.product.only_left', ['count' => $page->stock]) }}</span>
                    @endif
                    @if ($page->delivery)
                        <button type="button" class="sq-stock-cell text-sq-orange hover:underline" data-sheet-open="sq-delivery"><x-ui::icon name="truck" :size="20" />{{ $page->delivery['label'] }}</button>
                    @endif
                </div>

                @if ($page->summary || $page->description)
                    <div class="grid gap-2">
                        <p class="sq-clamp text-base leading-relaxed text-[#434655] lg:text-lg" data-collapsed data-clamp id="sq-summary">{{ $page->summary ?: \Illuminate\Support\Str::limit(strip_tags((string) $page->description), 400) }}</p>
                        <button type="button" class="inline-flex w-fit items-center gap-3 text-sm tracking-[0.5px] text-sq-primary" aria-expanded="false" aria-controls="sq-summary" data-clamp-toggle data-less="{{ __st('product.details_less') }}">
                            <span data-clamp-label>{{ __st('souqify_pages.product.see_more') }}</span><x-ui::icon name="chevron-down" :size="16" />
                        </button>
                    </div>
                @endif

                @if ($weight)
                    <button type="button" class="sq-freebox text-start" data-sheet-open="sf-free-shipping-sq">
                        <span class="flex items-center gap-2 text-sm font-bold text-sq-orange"><x-ui::icon name="truck" :size="16" />{{ __st('souqify_pages.product.free_shipping_over', ['weight' => $weight]) }}</span>
                        <span class="text-xs text-[#8f8f8f]">{{ __st('souqify_pages.product.free_shipping_note') }}</span>
                    </button>
                @endif

                @foreach ($page->attributes as $attribute)
                    <fieldset class="grid gap-2" data-attribute="{{ $attribute['id'] }}">
                        <legend class="mb-2 text-sm font-medium text-sq-ink">{{ $attribute['name'] }}: <span class="font-normal text-sf-muted" data-attribute-value>{{ collect($attribute['options'])->firstWhere('id', $selected['options'][$attribute['id']] ?? null)['name'] ?? '' }}</span></legend>
                        <div class="flex flex-wrap gap-2.5" role="radiogroup" aria-label="{{ $attribute['name'] }}">
                            @foreach ($attribute['options'] as $option)
                                @php($checked = ($selected['options'][$attribute['id']] ?? null) === $option['id'])
                                @if ($attribute['swatch'])
                                    <button type="button" role="radio" class="sq-swatch" data-swatch="{{ $option['swatch'] }}" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}" aria-label="{{ $option['name'] }}" title="{{ $option['name'] }}"></button>
                                @else
                                    <button type="button" role="radio" class="sq-option" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}">{{ $option['name'] }}</button>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                {{-- Add to cart + quantity (1:74695); fixed bottom bar on phones --}}
                <div class="sq-buybar">
                    <button type="button" class="sq-buy mo-press" data-add-to-cart data-product-id="{{ $page->view->id() }}" data-variant-id="{{ $page->selectedVariantId }}" data-state="idle" @disabled(! $page->inStock)>
                        <x-ui::icon name="cart-add" :size="24" />
                        <span><span data-cart-label>{{ $page->inStock ? __st('product.add_to_cart') : __st('product.out_of_stock') }}</span><span class="max-sm:hidden" data-sq-price-mirror @unless ($page->inStock) hidden @endunless> – {{ $page->price }}</span></span>
                    </button>
                    <div class="sq-qty" data-sq-qty-mirror data-max="{{ $maxQty }}">
                        <button type="button" data-step="-1" aria-label="{{ __st('souqify_pages.cart.decrease') }}" disabled><x-ui::icon name="minus" :size="22" /></button>
                        <label for="sq-qty" class="sr-only">{{ __st('product.qty') }}</label>
                        <input id="sq-qty" type="number" value="1" min="1" max="{{ $maxQty }}" readonly data-qty>
                        <button type="button" data-step="1" aria-label="{{ __st('souqify_pages.cart.increase') }}" @disabled($maxQty <= 1)><x-ui::icon name="plus" :size="22" /></button>
                    </div>
                    @if ($favoriteUrl)
                        <button type="button" class="sq-icon-btn hidden size-[68px] rounded-xl lg:grid" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="heart" :size="24" /></button>
                    @endif
                </div>
                <p class="text-sm text-sq-green" data-added-label hidden>{{ __st('product.added') }}</p>
                @unless ($page->inStock)
                    <p class="rounded-lg bg-sf-danger-bg px-3 py-2 text-sm text-sf-danger" role="status">{{ __st('product.out_of_stock_note') }}</p>
                @endunless
                <button type="button" class="hidden w-fit items-center gap-2 text-sm text-sf-muted hover:text-sq-ink lg:inline-flex" data-sq-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('souqify_pages.product.link_copied') }}"><x-ui::icon name="upload" :size="16" />{{ __st('souqify_pages.product.share') }}</button>
            </div>
        </div>

        {{-- Description / specifications (1:74707) --}}
        <section class="mt-10 grid gap-6 lg:mt-14 lg:gap-10" data-simple-tabs aria-label="{{ __st('souqify_pages.product.details') }}">
            <div class="sq-tabs" role="tablist">
                <button type="button" role="tab" id="sq-tab-desc" aria-controls="sq-panel-desc" aria-selected="true">{{ __st('souqify_pages.product.description') }}</button>
                @if ($specs !== [])
                    <button type="button" role="tab" id="sq-tab-specs" aria-controls="sq-panel-specs" aria-selected="false">{{ __st('souqify_pages.product.specs') }}</button>
                @endif
            </div>
            <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_359px] lg:gap-[38px]">
                <div class="sq-bento">
                    <div id="sq-panel-desc" role="tabpanel" aria-labelledby="sq-tab-desc" class="grid gap-6">
                        <h2 class="text-xl font-medium text-[#191b23] lg:text-2xl">{{ $page->name }}</h2>
                        @if ($hasDetails)
                            <div class="sq-prose">
                                @if ($page->description)
                                    {!! \App\Modules\Notifications\Support\EmailHtmlSanitizer::clean($page->description) !!}
                                @else
                                    <p>{{ $page->summary }}</p>
                                @endif
                            </div>
                        @else
                            <p class="sq-prose">{{ __st('souqify_pages.product.no_description') }}</p>
                        @endif
                    </div>
                    @if ($specs !== [])
                        <div id="sq-panel-specs" role="tabpanel" aria-labelledby="sq-tab-specs" class="grid gap-6" hidden>
                            <h2 class="text-xl font-medium text-[#191b23] lg:text-2xl">{{ __st('souqify_pages.product.specs') }}</h2>
                            <dl class="grid gap-6 sm:grid-cols-2">
                                @foreach ($specs as $spec)
                                    <div class="grid gap-2">
                                        <dt class="text-base font-medium text-sq-primary">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}. {{ $spec['name'] }}</dt>
                                        <dd class="text-sm tracking-[0.5px] text-[#434655]">{{ $spec['values'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
                <aside class="sq-guarantee" data-reveal="fade-up">
                    <div class="grid gap-4">
                        <div class="sq-guarantee-item">
                            <x-ui::icon name="truck" :size="22" class="text-sq-primary" />
                            <h3>{{ __st('souqify_pages.product.free_delivery') }}</h3>
                            <p>{{ $weight ? __st('souqify_pages.product.free_shipping_over', ['weight' => $weight]) : __st('cart.trust.guarantee.credit') }}</p>
                        </div>
                        <div class="sq-guarantee-item">
                            <x-ui::icon name="shield" :size="22" class="text-sq-primary" />
                            <h3>{{ __st('souqify_pages.product.easy_returns') }}</h3>
                            <p>
                                {{ __st('cart.trust.guarantee.damaged') }}
                                @if ($returns)<a href="{{ $returns }}" class="underline">{{ __st('souqify_pages.product.returns_policy') }}</a>@endif
                            </p>
                        </div>
                    </div>
                    @if ($badges)
                        <ul class="flex flex-wrap items-center justify-center gap-3" role="list" aria-label="{{ __st('souqify_pages.product.payment_methods') }}">
                            @foreach ($badges as $badge)
                                <li class="sq-pay-chip">{{ __st('shell.payments.'.$badge) }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <button type="button" class="inline-flex items-center justify-center gap-1 text-xs font-medium text-[#191b23] hover:underline" data-sheet-open="sq-privacy">{{ __st('souqify_pages.product.privacy_secure') }}<x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></button>
                </aside>
            </div>
        </section>

        {{-- User reviews (1:74838) --}}
        @if ($reviews['count'] > 0)
            <section class="mt-12 grid gap-6" aria-labelledby="sq-reviews-title" data-reviews data-reviews-url="{{ $reviewsUrl }}">
                <h2 id="sq-reviews-title" class="text-2xl font-medium text-sq-ink lg:text-[32px]" data-reveal="fade-up">{{ __st('souqify_pages.product.user_reviews') }}</h2>
                <div class="grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)] lg:gap-8">
                    <div class="sq-card grid content-center justify-items-center gap-1 p-6" data-reveal="zoom">
                        <p class="text-5xl font-semibold text-sq-ink">{{ number_format($reviews['average'], 1) }}</p>
                        <x-sf::stars :rating="$reviews['average']" :size="20" class="!text-sq-orange" />
                        <p class="text-xs text-sf-muted">{{ __st('souqify_pages.product.based_on', ['count' => number_format($reviews['count'])]) }}</p>
                    </div>
                    <ul class="grid content-center gap-2" role="list" data-reveal="fade-up">
                        @foreach ([5, 4, 3, 2, 1] as $star)
                            @php($share = (int) round(($reviews['distribution'][$star] ?? 0) / $reviews['count'] * 100))
                            <li class="flex items-center gap-2 text-xs text-sf-muted"><span class="w-8">{{ $star }} ★</span><span class="sq-review-bar"><span data-width="{{ $share }}"></span></span><span class="w-8 text-end">{{ $share }}%</span></li>
                        @endforeach
                    </ul>
                </div>
                @if ($reviewsUrl)
                    <div class="grid gap-4" data-reviews-preview aria-live="polite">
                        @for ($i = 0; $i < 2; $i++)
                            <div class="sq-card grid gap-2 p-5" aria-hidden="true"><span class="eco-skeleton h-5 w-40"></span><span class="eco-skeleton h-4 w-24"></span><span class="eco-skeleton h-5"></span></div>
                        @endfor
                    </div>
                    <div class="flex items-center gap-0">
                        <span class="h-px flex-1 bg-sq-line" aria-hidden="true"></span>
                        <button type="button" class="sq-btn sq-btn-outline mo-press h-12 px-8" data-sheet-open="sf-reviews">{{ __st('souqify_pages.product.see_all_reviews') }}<x-ui::icon name="arrow-right" :size="20" class="rtl:-scale-x-100" /></button>
                        <span class="h-px flex-1 bg-sq-line" aria-hidden="true"></span>
                    </div>
                @endif
            </section>
        @endif

        @if ($page->related !== [])
            <section class="mt-12 grid gap-6" aria-labelledby="sq-related-title">
                <div class="grid gap-6">
                    <div class="flex items-center justify-between gap-3" data-reveal="fade-up">
                        <h2 id="sq-related-title" class="text-2xl font-medium text-sq-ink lg:text-[28px]">{{ __st('souqify_pages.product.complete_collection') }}</h2>
                        <div class="flex items-center gap-2">
                            <a href="{{ $moreUrl }}" class="me-2 text-sm text-sq-ink hover:underline lg:hidden">{{ __st('souqify_pages.product.see_all') }}</a>
                            <button type="button" class="sq-rail-arrow" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" /></button>
                            <button type="button" class="sq-rail-arrow" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></button>
                        </div>
                    </div>
                    <div class="swiper -mx-4 px-4 lg:mx-0 lg:px-0" data-rail='{"spaceBetween":12,"breakpoints":{"1024":{"spaceBetween":24}}}' data-reveal="fade-up">
                        <ul class="swiper-wrapper" role="list">
                            @foreach ($page->related as $card)
                                <li class="swiper-slide !w-[172px] lg:!w-[300px]"><x-sf::product-card :card="$card" /></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        @if ($recommended !== [])
            <section class="mt-12 grid gap-6" aria-labelledby="sq-recommended-title">
                <div class="flex items-center justify-between gap-3" data-reveal="fade-up">
                    <h2 id="sq-recommended-title" class="text-2xl font-medium text-sq-ink lg:text-[32px]">{{ __st('souqify_pages.product.recommended') }}</h2>
                    <a href="{{ route('storefront.best-sellers') }}" class="inline-flex items-center gap-1 text-sm text-sq-ink hover:underline">{{ __st('souqify_pages.product.view_all') }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" /></a>
                </div>
                <div class="swiper -mx-4 px-4 lg:mx-0 lg:px-0" data-rail='{"spaceBetween":12,"breakpoints":{"1024":{"spaceBetween":24}}}' data-reveal="fade-up">
                    <ul class="swiper-wrapper" role="list">
                        @foreach ($recommended as $card)
                            <li class="swiper-slide !w-[172px] lg:!w-[300px]"><x-sf::product-card :card="$card" /></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>

    @if ($page->delivery)
        @include('storefront::themes.souqify.partials.delivery-estimate', ['page' => $page, 'peak' => $peak])
    @endif
    @include('storefront::themes.souqify.partials.privacy-popup')
    @if ($weight)
        <x-sf::modal id="sf-free-shipping-sq" :title="__st('shell.free_shipping.title')" size="sm">
            <ul class="grid gap-3 text-sm text-sq-ink" role="list">
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-sq-green" />{{ __st('souqify_pages.product.free_shipping_over', ['weight' => $weight]) }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-sq-green" />{{ __st('shell.free_shipping.line_2') }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-sq-green" />{{ __st('shell.free_shipping.line_3') }}</li>
            </ul>
        </x-sf::modal>
    @endif
    @if ($reviewsUrl)
        @include('storefront::themes.souqify.partials.reviews-modal', ['page' => $page])
    @endif
</x-sf::layout>
