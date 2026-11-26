{{-- Elora v1 orange category band (1:15432 under "Trending Now"): one swipeable row of round category photos on --elo-band. --}}
@props(['categories' => []])
@if ($categories !== [])
    <section class="elo-orange-band mt-12 py-6 max-lg:mt-6" aria-label="{{ __st('elora.home.categories') }}">
        <div class="elo-home-wrap">
            <div class="swiper !overflow-visible" data-rail='{"spaceBetween":25}'>
                <div class="swiper-wrapper">
                    @foreach ($categories as $category)
                        <div class="swiper-slide !w-[82px]" data-reveal="zoom">
                            <a href="{{ route('storefront.category', $category->slug) }}" class="elo-cat-circle group text-white">
                                <span class="elo-cat-circle-img"><x-sf::category-image :category="$category" :size="82" class="size-full object-cover transition duration-500 group-hover:scale-110" /></span>
                                <span class="line-clamp-2 text-center text-sm leading-tight">{{ $category->name }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
