{{--
    Elora card-fan / coverflow slider (Figma 1:30232; old-system-docs/home-pages-custom-sliders.md)
    — only where Figma shows it: v2 / v6 Best Seller and v2 flash sale (center), v3 Best Seller
    (left stack). core/motion/spotlight.js scales / tucks each card from its distance to the
    spotlight every frame; drag, wheel, keys, loop and RTL from Swiper.
--}}
@props(['cards' => [], 'layout' => 'center'])
@if ($cards !== [])
    @php($loop = count($cards) >= 10)
    <div {{ $attributes->class(['relative']) }}>
        <div class="swiper mo-spotlight elo-spotlight" data-spotlight='{"layout":"{{ $layout }}","loop":{{ $loop ? 'true' : 'false' }},"minScale":0.6,"step":{{ $layout === 'left' ? 0.09 : 0.18 }},"overlap":{{ $layout === 'left' ? 0.3 : 0.24 }}}' aria-roledescription="carousel">
            <div class="swiper-wrapper">
                @foreach ($cards as $card)
                    <div class="swiper-slide !h-auto">
                        <x-sf::elora.product-card :card="$card" class="elo-card-spot" />
                    </div>
                @endforeach
            </div>
        </div>
        <button type="button" class="elo-round-btn absolute start-0 top-1/2 z-[200] -translate-y-1/2 max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
        <button type="button" class="elo-round-btn absolute end-0 top-1/2 z-[200] -translate-y-1/2 max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
    </div>
@endif
