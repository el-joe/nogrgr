{{--
    Cards of one Elora listing page (grid children; also the load-more fragment). Variant 1
    draws the card of Figma 1:17172 / m 1:21453 (the v1 card with the "delivered by" line);
    variants 2–6 keep the family card.
    Inputs: $cards, $deliveryBy, $eager.
--}}
@php($v1 = app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme()->variant === 1)
@foreach ($cards as $index => $card)
    <div data-reveal="fade-up" class="min-w-0">
        @if ($v1)
            <x-sf::elora.v1.card :card="$card" size="grid" :delivery="$deliveryBy ?? null" :eager="($eager ?? false) && $index < 5" />
        @else
            <x-sf::product-card :card="$card" :eager="($eager ?? false) && $index < 5" />
        @endif
    </div>
@endforeach
