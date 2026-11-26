{{--
    Elora "Recommended For You" (v2–v6 bottom: several swipeable rows of cards; phone: a
    two-column grid of the first rows).
--}}
@props(['rows' => []])
@if ($rows !== [])
    <section class="elo-section" aria-labelledby="elo-rec-title">
        <div class="elo-home-wrap">
            <x-sf::elora.heading id="elo-rec-title" :title="__st('elora.home.recommended')" :more="route('storefront.new-in')" class="mb-6 max-lg:mb-4" />
            <div class="grid gap-6 max-lg:hidden">
                @foreach ($rows as $row)
                    <x-sf::elora.rail :cards="$row" />
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-3 lg:hidden" data-reveal-stagger>
                @foreach (array_slice(array_merge(...$rows), 0, 12) as $card)
                    <x-sf::elora.product-card :card="$card" data-reveal="fade-up" />
                @endforeach
            </div>
        </div>
    </section>
@endif
