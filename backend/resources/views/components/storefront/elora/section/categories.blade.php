{{--
    Elora "Categories" (store categories incl. store-own ones, store order — CategoryCatalog).
      circles  v1 1:15432: two centred rows of round photos + names (phone: 6 per row)
      tiles    v2 1:26666: white tiles, accent name, product cut-out; tall / stacked pairs
      cutout   v3 / v6: image over an accent blob (v3) or plain (v6), bold name under
      boxed    v4: square outlined tiles, name under
      slanted  v5: accent parallelogram with the name, image on the white end
    Every style except circles is a swipeable rail (touch + mouse drag).
--}}
@props(['categories' => [], 'style' => 'circles'])
@if ($categories !== [])
    @php($all = Route::has('storefront.categories.index') ? route('storefront.categories.index') : null)
    <section @class(['elo-cats mt-12 max-lg:mt-6', 'elo-cats-'.$style]) aria-labelledby="elo-cats-title">
        @if ($style === 'circles')
            <h2 id="elo-cats-title" class="sr-only">{{ __st('elora.home.categories') }}</h2>
            <div class="elo-home-wrap lg:hidden"><x-sf::elora.heading :title="__st('elora.home.categories')" :more="$all" class="mb-4" /></div>
            <ul class="elo-home-wrap grid grid-cols-6 gap-x-2 gap-y-4 lg:flex lg:flex-wrap lg:justify-center lg:gap-x-[25px] lg:gap-y-6" role="list" data-reveal-stagger>
                @foreach (array_slice($categories, 0, 22) as $index => $category)
                    <li data-reveal="zoom" @class(['max-lg:hidden' => $index >= 12])>
                        <a href="{{ route('storefront.category', $category->slug) }}" class="elo-cat-circle group">
                            <span class="elo-cat-circle-img"><x-sf::category-image :category="$category" :size="82" class="size-full object-cover transition duration-500 group-hover:scale-110" /></span>
                            <span class="line-clamp-2 text-center text-sm leading-tight max-lg:text-[10px]">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="elo-home-wrap"><x-sf::elora.heading id="elo-cats-title" :title="__st('elora.home.categories')" :more="$all" class="mb-6 max-lg:mb-4" /></div>
            <div class="elo-home-wrap">
                <div class="swiper !overflow-visible" data-rail='{"spaceBetween":{{ $style === 'tiles' ? 32 : 24 }}}'>
                    <div class="swiper-wrapper">
                        @if ($style === 'tiles')
                            @php($groups = [])
                            @php($rest = $categories)
                            @while ($rest !== [])
                                @php($size = count($groups) % 3 === 2 ? 2 : 1)
                                @php($groups[] = array_splice($rest, 0, $size))
                            @endwhile
                            @foreach ($groups as $group)
                                <div @class(['swiper-slide !h-auto', '!w-[150px] lg:!w-[167px]' => count($group) === 1, '!w-[220px] lg:!w-[260px]' => count($group) === 2]) data-reveal="fade-up">
                                    <div @class(['grid h-[200px] gap-6 lg:h-[227px]', 'grid-rows-2' => count($group) === 2])>
                                        @foreach ($group as $category)
                                            <a href="{{ route('storefront.category', $category->slug) }}" @class(['elo-cat-tile group', 'elo-cat-tile-wide' => count($group) === 2])>
                                                <span class="elo-cat-tile-name">{{ $category->name }}</span>
                                                <x-sf::category-image :category="$category" :size="140" class="elo-cat-tile-img" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @else
                            @foreach ($categories as $category)
                                <div @class(['swiper-slide !h-auto', '!w-[100px] lg:!w-[115px]' => in_array($style, ['cutout', 'boxed'], true), '!w-[150px] lg:!w-[150px]' => $style === 'slanted']) data-reveal="fade-up">
                                    <a href="{{ route('storefront.category', $category->slug) }}" class="elo-cat-{{ $style }} group">
                                        @if ($style === 'slanted')
                                            <span class="elo-cat-slanted-name">{{ $category->name }}</span>
                                            <span class="elo-cat-slanted-img"><x-sf::category-image :category="$category" :size="64" class="size-full object-contain transition duration-500 group-hover:scale-110" /></span>
                                        @else
                                            <span class="elo-cat-{{ $style }}-img"><x-sf::category-image :category="$category" :size="96" class="size-full object-contain transition duration-500 group-hover:scale-110" /></span>
                                            <span class="elo-cat-{{ $style }}-name">{{ $category->name }}</span>
                                        @endif
                                    </a>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </section>
@endif
