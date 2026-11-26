{{--
    Souqify "Recommended For You": style="rails" (v1: rows of large cards, each row a swipeable
    rail) or "grid" (v2–v6: five-column grid; phone two columns). Hidden when empty.
--}}
@props(['rows' => [], 'style' => 'grid'])
@php($cards = array_merge(...array_values($rows ?: [[]])))
@if ($cards !== [])
    @php($more = Route::has('storefront.new-in') ? route('storefront.new-in') : null)
    <section class="sq-section sq-recommended" aria-labelledby="sq-rec-title">
        <div class="sq-hwrap">
            <x-sf::souqify.heading id="sq-rec-title" :title="__st('souqify.home.recommended')" :more="$more" class="mb-6 max-lg:mb-3" />
            @if ($style === 'rails')
                <div class="grid gap-8 max-lg:gap-4">
                    @foreach ($rows as $row)
                        <x-sf::souqify.rail :cards="$row" :label="__st('souqify.home.recommended')" />
                    @endforeach
                </div>
            @else
                <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 lg:gap-x-4 lg:gap-y-6" role="list" data-reveal-stagger>
                    @foreach ($cards as $index => $card)
                        <li data-reveal="fade-up"><x-sf::souqify.product-card :card="$card" class="h-full" /></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
