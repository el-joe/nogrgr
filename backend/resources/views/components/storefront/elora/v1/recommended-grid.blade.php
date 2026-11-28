{{-- Elora v1 phone "Recommended For You" (m 1:21350): heading + two-column grid. --}}
@props(['cards' => [], 'title' => null, 'more' => null, 'delivery' => null])
@if ($cards !== [])
    <section {{ $attributes->class(['v1-section v1-section-center']) }} aria-label="{{ $title }}">
        <x-sf::elora.v1.heading :title="$title" icon="hand" style="center" :more="$more" />
        <div class="v1-grid" data-reveal-stagger>
            @foreach ($cards as $card)
                <x-sf::elora.v1.card :card="$card" size="grid" :delivery="$delivery" data-reveal="fade-up" />
            @endforeach
        </div>
    </section>
@endif
