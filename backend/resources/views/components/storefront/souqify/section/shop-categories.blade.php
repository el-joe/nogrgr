{{--
    Souqify "Shop by Category" (white band, centred title, "Explore all" outline pill).
      mosaic    v2: one large tile + two stacked tiles per group, accent name tab at the bottom
      slanted   v3: photo tiles with an accent triangle corner, name + store mark
      arch      v4: accent arch tiles, photo, white name at the bottom
      dots      v5: photo beside accent dot shapes, name under
      outline   v6: photo over an accent arch, accent name; the title is an outlined gradient
    Swipeable rail (touch + mouse drag, keys, RTL); hidden without categories.
--}}
@props(['categories' => [], 'style' => 'mosaic'])
@if ($categories !== [])
    @php $all = Route::has('storefront.categories.index') ? route('storefront.categories.index') : null; @endphp
    <section @class(['sq-shop', 'sq-shop-'.$style]) aria-labelledby="sq-shop-title">
        <div class="sq-hwrap">
            <h2 id="sq-shop-title" class="sq-shop-title" data-reveal="fade-up">{{ __st('souqify.home.shop_by_category') }}</h2>
            <div class="swiper !overflow-visible" data-rail='{"spaceBetween":{{ $style === 'mosaic' ? 12 : 16 }}}'>
                <div class="swiper-wrapper">
                    @if ($style === 'mosaic')
                        @php
                            $groups = [];
                            $rest = $categories;
                            while ($rest !== []) {
                                $groups[] = array_splice($rest, 0, count($groups) % 2 === 0 ? 1 : 2);
                            }
                        @endphp
                        @foreach ($groups as $group)
                            <div class="swiper-slide !h-auto !w-[170px] lg:!w-[322px]" data-reveal="fade-up">
                                <div @class(['grid h-[236px] gap-3 lg:h-[328px]', 'grid-rows-2' => count($group) === 2])>
                                    @foreach ($group as $category)
                                        <a href="{{ route('storefront.category', $category->slug) }}" class="sq-tile group">
                                            <x-sf::category-image :category="$category" :size="322" class="size-full object-contain bg-media transition duration-700 group-hover:scale-105" />
                                            <span class="sq-tile-name">{{ $category->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach ($categories as $category)
                            <div class="swiper-slide !h-auto sq-shop-slide" data-reveal="fade-up">
                                <a href="{{ route('storefront.category', $category->slug) }}" class="sq-shop-item group">
                                    <span class="sq-shop-art">
                                        <span class="sq-shop-shape" aria-hidden="true">@if ($style === 'slanted')<x-ui::icon name="sparkles" :size="28" />@endif</span>
                                        <span class="sq-shop-img"><x-sf::category-image :category="$category" :size="200" class="size-full object-contain bg-media transition duration-700 group-hover:scale-105" /></span>
                                    </span>
                                    <span class="sq-shop-name">{{ $category->name }}</span>
                                </a>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            @if ($all)
                <div class="mt-6 flex justify-center"><a href="{{ $all }}" class="sq-outline-btn mo-press">{{ __st('souqify.home.explore_all') }}</a></div>
            @endif
        </div>
    </section>
@endif
