{{--
    Souqify "Categories" (store categories incl. store-own ones, store order — CategoryCatalog).
      squares   v1 1:72942 "Browse by Categories": white band, rounded square photos + names,
                prev / next buttons in the heading
      cutout    v2: photo over an accent quarter-disc            pinwheel  v3: over a teal pinwheel
      arch      v4: over an accent arch                           dots      v5: beside accent dots
      outline   v6: under an accent arch outline
    Every style is a swipeable rail (touch + mouse drag, keys, RTL); hidden without categories.
--}}
@props(['categories' => [], 'style' => 'squares'])
@if ($categories !== [])
    @php($all = Route::has('storefront.categories.index') ? route('storefront.categories.index') : null)
    <section @class(['sq-cats', 'sq-cats-'.$style]) aria-labelledby="sq-cats-title">
        <div class="sq-hwrap">
            @if ($style === 'squares')
                <div class="mb-4 flex items-center justify-between gap-4" data-reveal="fade-up">
                    <h2 id="sq-cats-title" class="text-lg font-medium text-[#1b1b1b] max-lg:text-base">{{ __st('souqify.home.browse_categories') }}</h2>
                    <div class="flex gap-2">
                        <button type="button" class="sq-mini-btn mo-press" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="14" class="rtl:-scale-x-100" /></button>
                        <button type="button" class="sq-mini-btn mo-press" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></button>
                    </div>
                </div>
            @else
                <x-sf::souqify.heading id="sq-cats-title" :title="__st('souqify.home.categories')" :more="$all" class="mb-5 max-lg:mb-3" />
            @endif
            <div class="swiper !overflow-visible" id="sq-cats-rail" data-rail='{"spaceBetween":{{ $style === 'squares' ? 16 : 24 }}}'>
                <div class="swiper-wrapper">
                    @foreach ($categories as $category)
                        <div class="swiper-slide !h-auto sq-hcat-slide" data-reveal="zoom">
                            <a href="{{ route('storefront.category', $category->slug) }}" class="sq-hcat group">
                                <span class="sq-hcat-art">
                                    @if ($style !== 'squares')<span class="sq-hcat-shape" aria-hidden="true"></span>@endif
                                    <span class="sq-hcat-img"><x-sf::category-image :category="$category" :size="132" class="size-full object-cover transition duration-500 group-hover:scale-110" /></span>
                                </span>
                                <span class="sq-hcat-name">{{ $category->name }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
