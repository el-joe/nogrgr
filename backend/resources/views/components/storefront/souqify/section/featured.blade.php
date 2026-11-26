{{--
    Souqify "Featured Products" (v1 1:73040): indigo swoosh band, 3 × 2 card grid and a tall
    "N% · Best Deals · Shop now" panel at the end (N = best discount in the offers; the panel
    is left out when nothing is discounted). Phone: the panel becomes a strip above a rail.
--}}
@props(['cards' => [], 'offers' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = __st($section['title']);
        $more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null;
        $best = max(array_map(static fn ($card): int => (int) ($card->discount ?? 0), array_merge($offers, $cards)) + [0 => 0]);
        $offersUrl = Route::has('storefront.offers') ? route('storefront.offers') : null;
        $grid = array_slice($cards, 0, 6);
    @endphp
    <section class="sq-section sq-featured" aria-label="{{ $title }}">
        @if ($best > 0 && $offersUrl)
            <a href="{{ $offersUrl }}" class="sq-deal-strip lg:hidden">
                <span class="grid"><span class="text-4xl font-bold leading-none text-[#3d8bff]">{{ $best }}%</span><span class="text-xl uppercase">{{ __st('souqify.featured.label') }}</span><span class="text-lg font-semibold text-[#ff570f]">{{ __st('souqify.featured.best_deals') }}</span></span>
                <span class="sq-deal-btn">{{ __st('souqify.home.shop_now') }}</span>
            </a>
        @endif
        <div class="sq-hwrap py-8 max-lg:py-5">
            <x-sf::souqify.heading :title="$title" :more="$more" tone="light" class="mb-6 max-lg:mb-3" />
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,84px)] max-lg:hidden">
                <div class="grid grid-cols-3 gap-4" data-reveal-stagger>
                    @foreach ($grid as $card)<x-sf::souqify.product-card :card="$card" class="sq-pc-grid" data-reveal="fade-up" />@endforeach
                </div>
                @if ($best > 0 && $offersUrl)
                    <a href="{{ $offersUrl }}" class="sq-deal-panel" data-reveal="slide-start">
                        <span class="text-[56px] font-bold leading-none">{{ $best }}<br>%</span>
                        <span class="my-4 h-px w-12 bg-white/70" aria-hidden="true"></span>
                        <span class="text-center text-xl uppercase leading-tight">{{ __st('souqify.featured.label') }}</span>
                        <span class="text-sm font-semibold">{{ __st('souqify.featured.best_deals') }}</span>
                        <span class="sq-deal-btn mt-6">{{ __st('souqify.home.shop_now') }}</span>
                    </a>
                @endif
            </div>
            <x-sf::souqify.rail :cards="$grid" :label="$title" class="lg:hidden" />
        </div>
    </section>
@endif
