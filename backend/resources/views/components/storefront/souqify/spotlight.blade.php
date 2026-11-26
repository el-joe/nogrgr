{{--
    Souqify custom sliders (old-system-docs/home-pages-custom-sliders.md) — only where the Figma
    shows them: layout="fan" (v4 flash / v5 best seller: centred card fan, neighbours tilted and
    tucked behind) or "left" (v4 best seller / v6 flash: progressive scaling from the start).
    core/motion/spotlight.js computes scale / tuck / tilt per frame; drag, wheel, keys, loop, RTL.
--}}
@props(['cards' => [], 'layout' => 'fan'])
@if ($cards !== [])
    @php
        $loop = count($cards) >= 8;
        $config = $layout === 'left'
            ? ['layout' => 'left', 'loop' => $loop, 'minScale' => 0.55, 'step' => 0.1, 'overlap' => 0.32]
            : ['layout' => 'center', 'loop' => $loop, 'minScale' => 0.7, 'step' => 0.12, 'overlap' => 0.3, 'tilt' => 6];
    @endphp
    <div {{ $attributes->class(['sq-spot relative min-w-0', 'sq-spot-'.$layout]) }}>
        <div class="swiper mo-spotlight" data-spotlight='@json($config)' aria-roledescription="carousel">
            <div class="swiper-wrapper">
                @foreach ($cards as $card)
                    <div class="swiper-slide !h-auto">
                        <div><x-sf::souqify.product-card :card="$card" class="sq-pc-spot" /></div>
                    </div>
                @endforeach
            </div>
        </div>
        <button type="button" class="sq-round-btn absolute start-0 top-1/2 z-[200] -translate-y-1/2 max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
        <button type="button" class="sq-round-btn absolute end-0 top-1/2 z-[200] -translate-y-1/2 max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
    </div>
@endif
