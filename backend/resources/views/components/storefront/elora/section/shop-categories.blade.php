{{--
    Elora "Shop by Category" (store categories incl. store-own ones, store order).
      cards     v1: orange band, white-footed photo cards in a rail
      mosaic    v2 / v3: tall tiles + stacked pairs, name over an accent gradient, "Explore all"
      side      v4: vertical accent title at the start + the mosaic
      portrait  v5: tall photo tiles with the name and a "Shop ›" pill
      small     v6: small photo tiles with the name over a dark fade
    Every style swipes (touch + mouse drag); photo-led, so only categories with an image show
    and the section hides when none has one.
--}}
@props(['categories' => [], 'style' => 'mosaic'])
@php($withImage = array_values(array_filter($categories, static fn ($category) => $category->thumbnailUrl() !== null)))
@php($items = $withImage)
@if ($items !== [])
    @php($all = Route::has('storefront.categories.index') ? route('storefront.categories.index') : null)
    @if ($style === 'cards')
        <section class="elo-orange-band mt-12 py-8 max-lg:mt-6" aria-labelledby="elo-shop-cats">
            <div class="elo-home-wrap">
                <h2 id="elo-shop-cats" class="mb-8 flex items-center justify-center gap-3 text-2xl font-semibold text-white max-lg:mb-4 max-lg:text-lg" data-reveal="fade-up"><x-ui::icon name="layers" :size="28" />{{ __st('elora.home.shop_by_category') }}</h2>
                <div class="swiper !overflow-visible" data-rail='{"spaceBetween":18}'>
                    <div class="swiper-wrapper">
                        @foreach ($items as $category)
                            <div class="swiper-slide !h-auto !w-[140px] lg:!w-[177px]" data-reveal="fade-up">
                                <a href="{{ route('storefront.category', $category->slug) }}" class="elo-shop-card group">
                                    <span class="elo-shop-card-img"><x-sf::category-image :category="$category" :size="177" class="size-full object-contain bg-media transition duration-700 group-hover:scale-105" /></span>
                                    <span class="elo-shop-card-name">{{ $category->name }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="elo-section bg-white" aria-labelledby="elo-shop-cats">
            <div @class(['elo-home-wrap', 'lg:grid lg:grid-cols-[48px_minmax(0,1fr)] lg:gap-8' => $style === 'side'])>
                <h2 id="elo-shop-cats" @class(['text-[32px] font-medium text-sf-ink max-lg:text-xl', 'mb-6 text-center' => $style !== 'side', 'elo-side-title max-lg:mb-4' => $style === 'side']) data-reveal="fade-up">{{ __st('elora.home.shop_by_category') }}</h2>
                <div>
                    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":12}'>
                        <div class="swiper-wrapper">
                            @if ($style === 'mosaic' || $style === 'side')
                                @php($groups = [])
                                @php($rest = $items)
                                @while ($rest !== [])
                                    @php($groups[] = array_splice($rest, 0, count($groups) % 2 === 0 ? 1 : 2))
                                @endwhile
                                @foreach ($groups as $group)
                                    <div class="swiper-slide !h-auto !w-[220px] lg:!w-[323px]" data-reveal="fade-up">
                                        <div @class(['grid h-[260px] gap-3 lg:h-[326px]', 'grid-rows-2' => count($group) === 2])>
                                            @foreach ($group as $category)
                                                <a href="{{ route('storefront.category', $category->slug) }}" class="elo-mosaic-tile group">
                                                    <x-sf::category-image :category="$category" :size="323" class="size-full object-contain bg-media transition duration-700 group-hover:scale-105" />
                                                    <span class="elo-mosaic-name">{{ $category->name }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                @foreach ($items as $category)
                                    <div @class(['swiper-slide !h-auto', '!w-[160px] lg:!w-[210px]' => $style === 'portrait', '!w-[110px] lg:!w-[134px]' => $style === 'small']) data-reveal="fade-up">
                                        <a href="{{ route('storefront.category', $category->slug) }}" class="elo-{{ $style }}-tile group">
                                            <x-sf::category-image :category="$category" :size="210" class="size-full object-contain bg-media transition duration-700 group-hover:scale-105" />
                                            <span class="elo-{{ $style }}-name">{{ $category->name }}@if ($style === 'portrait')<span class="elo-portrait-shop">{{ __st('elora.home.shop') }}<x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></span>@endif</span>
                                        </a>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @if ($all)
                <div class="mt-6 flex justify-center"><a href="{{ $all }}" class="elo-outline-btn elo-outline-accent mo-press">{{ __st('elora.home.explore_all') }}</a></div>
            @endif
        </section>
    @endif
@endif
