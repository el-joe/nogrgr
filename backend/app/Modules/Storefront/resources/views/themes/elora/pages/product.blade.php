{{--
    Elora product details (Figma 1:16245 / m 1:21580; compact variant without reviews
    1:16088): free-shipping bar, gallery (swipe, mouse drag, pinch / double-tap zoom, arrows,
    thumbnail strip, counter), title, rating, live price / compare / discount, flash
    countdown, estimated delivery (→ delivery popup m 1:23053), variation chips, qty +
    add to cart (fly-to-cart) + favourite + share, trust cards (→ "Why choose" popup
    m 1:23152), product details (see more), reviews preview + modal (135:111928 / write
    135:112064 / m 135:112101) and the "Similar items" rail. Phones: fixed bottom bar with
    a quantity stepper and the orange add-to-cart button.
    Data as the Ecommet product page: $page (ProductPageData), $seo.
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ProductPageData $page */
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $images = $page->images;
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
    $moreUrl = $page->category ? route('storefront.category', ['slug' => $page->category->slug]) : route('storefront.best-sellers');
@endphp
<x-sf::layout :seo="$seo" page="product">
    <script type="application/json" id="sf-product">{!! json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="elo-narrow pb-36 pt-3 lg:pb-16 lg:pt-6" data-product-page data-product="{{ $page->view->id() }}">
        {{-- Phone bar: back, search, favourite, share (m 1:21580) --}}
        <div class="mb-3 flex items-center gap-2.5 lg:hidden">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('storefront.home') }}" class="elo-icon-btn" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></a>
            <form method="GET" action="{{ route('storefront.search') }}" role="search" class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-full border border-elo-line px-3">
                <x-ui::icon name="search" :size="16" class="text-sf-muted" />
                <label for="elo-p-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
                <input id="elo-p-search" type="search" name="q" placeholder="{{ __st('shell.search') }}" class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none" enterkeyhint="search">
            </form>
            @if ($favoriteUrl)
                <button type="button" class="elo-icon-btn" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="heart" :size="20" /></button>
            @endif
            <button type="button" class="elo-icon-btn" data-elo-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('elora_pages.product.link_copied') }}" aria-label="{{ __st('elora_pages.product.share') }}"><x-ui::icon name="upload" :size="18" /></button>
        </div>

        @if ($weight)
            <button type="button" class="elo-freebar mb-6 hidden w-full lg:flex" data-sheet-open="sf-free-shipping-elo" data-reveal="fade">{{ __st('elora_pages.product.free_shipping_over', ['weight' => $weight]) }}</button>
        @endif

        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-5 hidden lg:block">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                @foreach ($page->breadcrumbs as $crumb)
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.category', ['slug' => $crumb->slug]) }}">{{ $crumb->name }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                @endforeach
                <li class="max-w-[240px] truncate" aria-current="page">{{ $page->name }}</li>
            </ol>
        </nav>

        <div @class([
            'grid gap-5 lg:gap-9',
            'lg:grid-cols-[minmax(0,695px)_minmax(0,1fr)] lg:gap-12' => ! $compact,
            'lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)_216px]' => $compact,
        ])>
            {{-- Gallery + details + reviews --}}
            <div class="grid content-start gap-5 max-lg:contents">
                <div class="grid gap-3 max-lg:order-1" data-gallery>
                    <div class="relative">
                        <div class="swiper elo-gallery-main aspect-[492/280] max-lg:aspect-[343/215]" data-gallery-main>
                            <div class="swiper-wrapper">
                                @forelse ($images as $index => $image)
                                    <div class="swiper-slide">
                                        <div class="swiper-zoom-container">
                                            <img src="{{ $image }}" alt="{{ $page->name }}{{ $index ? ' ('.($index + 1).')' : '' }}" width="984" height="560" class="size-full object-contain bg-media" @if ($index === 0) fetchpriority="high" loading="eager" data-fly-image @else loading="lazy" @endif decoding="async">
                                        </div>
                                    </div>
                                @empty
                                    <div class="swiper-slide grid place-items-center text-elo-line"><x-ui::icon name="image" :size="64" data-fly-image /></div>
                                @endforelse
                            </div>
                        </div>
                        @if ($page->bestSelling)
                            <a href="{{ route('storefront.best-sellers') }}" class="elo-tag absolute start-0 top-2 z-10 rounded-e-sm rounded-s-none">{{ __st('catalog.card.best_selling_tag') }}</a>
                        @endif
                        @if (count($images) > 1)
                            <button type="button" class="elo-icon-btn absolute -start-4 top-1/2 z-10 hidden size-[30px] -translate-y-1/2 lg:grid" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" /></button>
                            <button type="button" class="elo-icon-btn absolute -end-4 top-1/2 z-10 hidden size-[30px] -translate-y-1/2 lg:grid" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></button>
                            <span class="absolute bottom-2 end-2 z-10 rounded-md bg-black/60 px-2 py-0.5 text-xs text-white" data-gallery-counter aria-hidden="true">1/{{ count($images) }}</span>
                        @endif
                    </div>
                    @if (count($images) > 1)
                        <div class="swiper w-full" data-gallery-thumbs='{"slidesPerView":4.5,"spaceBetween":10,"breakpoints":{"640":{"slidesPerView":7}}}'>
                            <div class="swiper-wrapper">
                                @foreach ($images as $index => $image)
                                    <div class="swiper-slide cursor-pointer @if ($index === 0) is-active @endif">
                                        <span class="elo-thumb"><img src="{{ $image }}" alt="" width="126" height="112" loading="lazy" decoding="async"></span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @if (! $compact && ($page->description || $page->summary))
                    <section class="grid gap-3 max-lg:order-5" aria-labelledby="elo-details-title" data-reveal="fade-up">
                        <h2 id="elo-details-title" class="text-xl font-medium text-elo-ink lg:text-2xl">{{ __st('elora_pages.product.details') }}</h2>
                        <div class="elo-prose" data-collapsed data-clamp id="elo-details-body">
                            @if ($page->description)
                                {!! \App\Modules\Notifications\Support\EmailHtmlSanitizer::clean($page->description) !!}
                            @else
                                <p>{{ $page->summary }}</p>
                            @endif
                        </div>
                        <button type="button" class="inline-flex w-fit items-center gap-1 text-sm text-elo-primary" aria-expanded="false" aria-controls="elo-details-body" data-clamp-toggle data-less="{{ __st('product.details_less') }}">
                            <span data-clamp-label>{{ __st('elora_pages.product.see_more') }}</span><x-ui::icon name="chevron-down" :size="16" />
                        </button>
                    </section>
                @endif

                @unless ($compact)
                    <section class="grid gap-4 max-lg:order-6" aria-labelledby="elo-reviews-title" data-reviews data-reviews-url="{{ $reviewsUrl }}">
                        <h2 id="elo-reviews-title" class="text-xl font-medium text-elo-ink lg:sr-only">{{ __st('elora_pages.product.reviews_rating') }}</h2>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-elo-line pb-4">
                            <p class="text-base text-elo-ink">{{ __st('product.reviews.count', ['count' => $reviews['count']]) }}</p>
                            <span class="h-6 w-px bg-elo-line" aria-hidden="true"></span>
                            <p class="flex items-center gap-2 text-xl text-elo-ink">{{ number_format($reviews['average'], 1) }}<x-sf::stars :rating="$reviews['average']" :size="24" tone="dark" /></p>
                            <p class="inline-flex items-center gap-2 rounded bg-[#eaf7eb] px-3 py-1.5 text-xs text-elo-green max-lg:order-first max-lg:w-full max-lg:justify-center"><x-ui::icon name="check-circle" :size="16" />{{ __st('elora_pages.product.verified_note') }}</p>
                        </div>
                        @if ($reviewsUrl)
                            <div class="grid divide-y divide-elo-line" data-reviews-preview aria-live="polite">
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="grid gap-2 py-4" aria-hidden="true"><span class="eco-skeleton h-5 w-40"></span><span class="eco-skeleton h-4 w-24"></span><span class="eco-skeleton h-5"></span></div>
                                @endfor
                            </div>
                            <button type="button" class="elo-btn elo-btn-ink mo-press mx-auto h-9 min-h-9 w-[150px] text-sm" data-sheet-open="sf-reviews">{{ __st('elora_pages.product.see_all_reviews') }}</button>
                        @endif
                    </section>
                @endunless
            </div>

            {{-- Buy box --}}
            <div class="grid content-start gap-4 max-lg:order-2">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($fastest && $page->delivery)
                        <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-full bg-elo-green px-3 text-xs text-white lg:hidden" data-sheet-open="elo-delivery"><x-ui::icon name="truck" :size="14" />{{ __st('elora_pages.product.fastest', ['days' => $fastest['day']]) }}</button>
                    @endif
                    <h1 class="text-xl font-medium leading-snug text-elo-ink lg:text-[26px]" data-title>{{ $page->name }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <button type="button" class="inline-flex items-center gap-1.5 text-elo-ink" @if ($reviewsUrl && $reviews['count'] > 0) data-sheet-open="sf-reviews" @else disabled @endif>
                        <x-ui::icon name="star" :size="16" class="fill-[#ffb00a] text-[#ffb00a]" />{{ number_format($reviews['average'], 1) }}
                        <span class="text-sf-muted">{{ __st('elora_pages.product.rates', ['count' => $reviews['count']]) }}</span>
                    </button>
                    @if ($page->soldCount > 0)
                        <span class="text-sf-muted">{{ $page->soldCount >= 1000 ? __st('product.sold_k', ['count' => rtrim(rtrim(number_format($page->soldCount / 1000, 1), '0'), '.')]) : __st('product.sold', ['count' => $page->soldCount]) }}</span>
                    @endif
                    @if ($page->flash && ($page->flash['ends_at'] ?? '') !== '')
                        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-elo-primary px-3 text-xs text-white">
                            <x-ui::icon name="clock" :size="14" />{{ __st('elora_pages.product.flash_ends') }}
                            <span class="font-semibold tabular-nums" data-countdown="{{ $page->flash['ends_at'] }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}"><span data-unit="days">00</span>:<span data-unit="hours">00</span>:<span data-unit="minutes">00</span>:<span data-unit="seconds">00</span></span>
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 max-lg:flex-row-reverse max-lg:justify-end" aria-live="polite">
                    <span class="text-xl text-sf-muted line-through max-lg:text-sm" data-compare @if (! $page->compare) hidden @endif><span class="sr-only">{{ __st('catalog.card.was') }}</span><span data-compare-value>{{ $page->compare }}</span></span>
                    <span class="text-2xl font-medium text-elo-ink max-lg:order-last max-lg:w-auto"><span class="sr-only">{{ __st('catalog.card.now') }}</span><span data-price>{{ $page->price }}</span></span>
                    <span class="elo-off" data-discount @if (! $page->discount) hidden @endif>{{ __st('product.discount_off', ['percent' => $page->discount ?? 0]) }}</span>
                </div>

                @if ($page->delivery)
                    <button type="button" class="inline-flex w-fit items-center gap-2 text-sm text-elo-green hover:underline" data-sheet-open="elo-delivery">
                        <x-ui::icon name="truck-tick" :size="20" />{{ __st('elora_pages.product.estimated', ['range' => $page->delivery['label']]) }}
                    </button>
                @endif

                @if ($compact && ($page->summary || $page->description))
                    <p class="text-sm leading-relaxed text-[#5e5e5e]">{{ $page->summary ?: \Illuminate\Support\Str::limit(strip_tags((string) $page->description), 220) }}</p>
                @endif

                @foreach ($page->attributes as $attribute)
                    <fieldset class="grid gap-2" data-attribute="{{ $attribute['id'] }}">
                        <legend class="mb-2 text-sm text-elo-ink">{{ $attribute['name'] }}: <span class="text-sf-muted" data-attribute-value>{{ collect($attribute['options'])->firstWhere('id', $selected['options'][$attribute['id']] ?? null)['name'] ?? '' }}</span></legend>
                        <div class="flex flex-wrap gap-2.5" role="radiogroup" aria-label="{{ $attribute['name'] }}">
                            @foreach ($attribute['options'] as $option)
                                @php($checked = ($selected['options'][$attribute['id']] ?? null) === $option['id'])
                                @if ($attribute['swatch'])
                                    <button type="button" role="radio" class="elo-swatch" data-swatch="{{ $option['swatch'] }}" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}" aria-label="{{ $option['name'] }}" title="{{ $option['name'] }}"></button>
                                @else
                                    <button type="button" role="radio" class="elo-option" data-option="{{ $option['id'] }}" aria-checked="{{ $checked ? 'true' : 'false' }}">{{ $option['name'] }}</button>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="hidden items-center gap-4 lg:flex">
                    <label for="elo-qty" class="text-sm text-elo-ink">{{ __st('product.qty') }}</label>
                    <select id="elo-qty" class="h-7 w-14 rounded border border-elo-line bg-white px-1.5 text-sm" data-qty data-elo-qty-source>
                        @for ($q = 1; $q <= $maxQty; $q++)<option value="{{ $q }}">{{ $q }}</option>@endfor
                    </select>
                    <span class="text-sm text-elo-ink" data-added-label hidden>{{ __st('product.added') }}</span>
                </div>

                <div class="elo-buybar lg:mt-2">
                    <div class="elo-stepper h-10 shrink-0 lg:hidden" data-elo-qty-mirror data-max="{{ $maxQty }}">
                        <button type="button" data-step="-1" aria-label="{{ __st('elora_pages.cart.decrease') }}" disabled><x-ui::icon name="minus" :size="16" /></button>
                        <input type="number" value="1" min="1" max="{{ $maxQty }}" readonly tabindex="-1" aria-label="{{ __st('product.qty') }}" class="!w-8 !bg-transparent !text-base !text-elo-ink">
                        <button type="button" data-step="1" aria-label="{{ __st('elora_pages.cart.increase') }}" @disabled($maxQty <= 1)><x-ui::icon name="plus" :size="16" /></button>
                    </div>
                    <button type="button" class="elo-btn relative h-11 flex-1 bg-elo-primary text-white hover:bg-[var(--nog-primary-hover)] disabled:cursor-not-allowed lg:h-[38px] lg:max-w-[290px] lg:bg-elo-ink lg:hover:bg-black" data-add-to-cart data-product-id="{{ $page->view->id() }}" data-variant-id="{{ $page->selectedVariantId }}" data-state="idle" @disabled(! $page->inStock)>
                        <x-ui::icon name="cart-add" :size="20" /><span data-cart-label>{{ $page->inStock ? __st('product.add_to_cart') : __st('product.out_of_stock') }}</span>
                    </button>
                    @if ($favoriteUrl)
                        <button type="button" class="elo-icon-btn hidden lg:grid" data-favorite data-favorite-url="{{ $favoriteUrl }}" data-product-id="{{ $page->view->id() }}" aria-pressed="{{ $page->favorited ? 'true' : 'false' }}" aria-label="{{ __st('product.favorite') }}"><x-ui::icon name="heart" :size="20" /></button>
                    @endif
                    <button type="button" class="elo-icon-btn hidden lg:grid" data-elo-share data-share-title="{{ $page->name }}" data-share-url="{{ $page->url }}" data-share-done="{{ __st('elora_pages.product.link_copied') }}" aria-label="{{ __st('elora_pages.product.share') }}"><x-ui::icon name="upload" :size="18" /></button>
                </div>
                @unless ($page->inStock)
                    <p class="rounded-lg bg-sf-danger-bg px-3 py-2 text-sm text-sf-danger" role="status">{{ __st('product.out_of_stock_note') }}</p>
                @endunless

                @if ($weight)
                    <button type="button" class="-mx-4 flex items-center justify-center gap-3 bg-[#eaf7eb] px-4 py-4 text-sm text-elo-green lg:hidden" data-sheet-open="sf-free-shipping-elo"><x-ui::icon name="gift" :size="24" />{{ __st('elora_pages.product.free_shipping_over', ['weight' => $weight]) }}</button>
                @endif

                @unless ($compact)
                    <x-sf::elora.inner.product-trust class="mt-4" />
                @endunless
            </div>

            @if ($compact)
                <x-sf::elora.inner.product-trust layout="stack" class="max-lg:order-3 lg:content-start" />
            @endif
        </div>

        @if ($page->related !== [])
            <section class="mt-12 grid gap-4" aria-labelledby="elo-related-title">
                <div class="flex items-center justify-between gap-3" data-reveal="fade-up">
                    <h2 id="elo-related-title" class="text-lg font-medium text-elo-ink lg:text-xl">{{ __st('elora_pages.product.similar') }}</h2>
                    <a href="{{ $moreUrl }}" class="text-xs text-elo-primary hover:underline">{{ __st('elora_pages.product.see_all') }}</a>
                </div>
                <div class="swiper -mx-4 px-4 lg:mx-0 lg:px-0" data-rail='{"spaceBetween":12,"breakpoints":{"1024":{"spaceBetween":16}}}' data-reveal="fade-up">
                    <ul class="swiper-wrapper" role="list">
                        @foreach ($page->related as $card)
                            <li class="swiper-slide !w-[160px] lg:!w-[212px]"><x-sf::product-card :card="$card" /></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>

    @if ($page->delivery)
        @include('storefront::themes.elora.partials.delivery-estimate', ['page' => $page, 'peak' => $peak])
    @endif
    @include('storefront::themes.elora.partials.privacy-popup')
    @if ($weight)
        <x-sf::modal id="sf-free-shipping-elo" :title="__st('shell.free_shipping.title')" size="sm">
            <ul class="grid gap-3 text-sm text-elo-ink" role="list">
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ __st('elora_pages.product.free_shipping_over', ['weight' => $weight]) }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ __st('shell.free_shipping.line_2') }}</li>
                <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-elo-green" />{{ __st('shell.free_shipping.line_3') }}</li>
            </ul>
        </x-sf::modal>
    @endif
    @if ($reviewsUrl)
        @include('storefront::themes.elora.partials.reviews-modal', ['page' => $page])
    @endif
</x-sf::layout>
