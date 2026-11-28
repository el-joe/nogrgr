{{--
    Elora v1 categories (1:15435: two centred rows of round photos; m 1:21127: heading,
    "see all" and two swipeable rows).
--}}
@props(['tiles' => []])
@if ($tiles !== [])
    @php($all = Route::has('storefront.categories.index') ? route('storefront.categories.index') : null)
    <section {{ $attributes->class(['v1-cats']) }} aria-labelledby="v1-cats-title">
        <div class="v1-cats-head lg:sr-only">
            <h2 id="v1-cats-title">{{ __st('elora.home.categories') }}</h2>
            @if ($all)<a href="{{ $all }}" class="lg:hidden">{{ __st('elora.home.see_all') }}</a>@endif
        </div>
        <ul class="v1-cats-grid max-lg:hidden" role="list" data-reveal="fade-up">
            @foreach ($tiles as $tile)
                <li><x-sf::elora.v1.circle :tile="$tile" /></li>
            @endforeach
        </ul>
        <div class="lg:hidden">
        <div class="swiper" data-rail='{"spaceBetween":4,"slidesOffsetBefore":16,"slidesOffsetAfter":16}'>
            <div class="swiper-wrapper">
                @foreach (array_chunk($tiles, 2) as $column)
                    <div class="swiper-slide v1-cats-column">
                        @foreach ($column as $tile)
                            <x-sf::elora.v1.circle :tile="$tile" />
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        </div>
    </section>
@endif
