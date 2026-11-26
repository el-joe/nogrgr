{{-- Cards of one listing page (grid children; also returned as the "See more" fragment). --}}
@foreach ($cards as $index => $card)
    <div data-reveal="fade-up" class="min-w-0">
        <x-sf::product-card :card="$card" :variant="$variant ?? 'grid'" :tag="$tag ?? null" :ribbon="$ribbon ?? false" :eager="($eager ?? false) && $index < 5" />
    </div>
@endforeach
