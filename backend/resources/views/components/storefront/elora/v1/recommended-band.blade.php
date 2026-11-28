{{-- Elora v1 phone "Recommended for You" band (m 1:21278): small cards on the gradient. --}}
@props(['cards' => [], 'title' => null])
@if ($cards !== [])
    <section {{ $attributes->class(['v1-mini-band v1-grad-bg']) }} aria-label="{{ $title }}">
        <h2>{{ $title }}</h2>
        <div class="swiper" data-rail='{"spaceBetween":8,"slidesOffsetBefore":16,"slidesOffsetAfter":16}'>
            <div class="swiper-wrapper">
                @foreach ($cards as $card)
                    <div class="swiper-slide v1-mini-slide"><x-sf::elora.v1.card :card="$card" size="mini" /></div>
                @endforeach
            </div>
        </div>
    </section>
@endif
