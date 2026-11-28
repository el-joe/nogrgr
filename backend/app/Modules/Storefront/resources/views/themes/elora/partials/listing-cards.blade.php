{{--
    Cards of one Elora v1 listing page (grid children; also the load-more fragment): the card
    of Figma 1:17172 / m 1:21453 with the "delivered by" line.
    Inputs: $cards, $deliveryBy, $eager.
--}}
@foreach ($cards as $index => $card)
    <div data-reveal="fade-up" class="min-w-0">
        <x-sf::elora.v1.card :card="$card" size="grid" :delivery="$deliveryBy ?? null" :eager="($eager ?? false) && $index < 5" />
    </div>
@endforeach
