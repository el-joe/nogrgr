{{-- Elora v1 phone "Recommended for You" band (m 1:21278): the rail cards on the gradient, two per view. --}}
@props(['cards' => [], 'title' => null])
@if ($cards !== [])
    <section {{ $attributes->class(['v1-mini-band v1-grad-bg']) }} aria-label="{{ $title }}">
        <h2>{{ $title }}</h2>
        <x-sf::elora.v1.rail :cards="$cards" kind="band" :label="$title" />
    </section>
@endif
