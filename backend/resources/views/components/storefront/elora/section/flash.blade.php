{{--
    Elora flash sale (live sale of the shopping country that ends first + its products).
      band   v1: promo banner ('home.promo'), gradient band with h / m / s boxes and the sale
             name, then the deal rail over the band
      fan    v2: purple panel — huge "Flash Sale", "up to N%", alarm countdown, "Explore all";
             the deals in the card-fan spotlight (1:30232)
      mixed  v3: gradient panel, centred title, countdown, large card rail, "Shop now"
      grid   v4 / v6: two rows of wide cards; v4 countdown + full-width "Shop now" under it,
             v6 big "FLASH SALE" + "N% OFF" + countdown at the start
      tiles  v5: pink panel, title + "Ends in" boxes + arrow, image tiles with -N% chips
    Hidden without a live sale or without sellable deal products.
--}}
@props(['sale' => null, 'deals' => [], 'promo' => [], 'style' => 'band'])
@if ($sale && $deals !== [])
    @php
        $more = route('storefront.offers', ['flash' => 1]);
        $upTo = max(array_map(static fn ($card) => (int) ($card->discount ?? 0), $deals) + [0 => (int) $sale->percentage]);
        $title = $sale->title !== '' ? $sale->title : __st('elora.flash.title');
    @endphp
    @if ($style === 'band')
        <section class="mt-12 max-lg:mt-6" aria-labelledby="elo-flash-title">
            @if ($banner = ($promo[0] ?? null))
                <a href="{{ $banner->url ?? $more }}" class="block overflow-hidden" data-reveal="fade">
                    <picture>
                        @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                        <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? $title }}" width="1440" height="148" loading="lazy" decoding="async" class="mo-kenburns h-[148px] w-full object-cover max-lg:h-[48px]">
                    </picture>
                </a>
            @endif
            <div class="elo-flash-band">
                <div class="elo-home-wrap flex flex-wrap items-center justify-between gap-4 py-5 max-lg:py-2">
                    <h2 id="elo-flash-title" class="sr-only">{{ $title }}</h2>
                    <x-sf::elora.countdown :ends-at="$sale->endsAt" suffix class="elo-countdown-square" />
                    <p class="max-w-[200px] text-sm text-white/90 max-lg:hidden">{{ $title }}</p>
                </div>
                <div class="elo-home-wrap pb-6"><x-sf::elora.rail :cards="$deals" /></div>
            </div>
        </section>
    @elseif ($style === 'fan')
        <section class="elo-flash-panel mt-12 overflow-hidden max-lg:mt-6" aria-labelledby="elo-flash-title">
            <div class="elo-home-wrap grid grid-cols-[minmax(0,1fr)] items-center gap-8 py-10 lg:grid-cols-[minmax(0,470px)_minmax(0,1fr)]">
                <div class="grid content-center gap-6 max-lg:justify-items-center max-lg:text-center" data-reveal="slide-start">
                    <h2 id="elo-flash-title" class="grid leading-none">
                        <span class="whitespace-nowrap text-[clamp(48px,7vw,112px)] font-semibold">{{ __st('elora.flash.title') }}</span>
                        @if ($upTo > 0)<span class="mt-2 text-6xl font-semibold text-[#ffd428] max-lg:text-2xl">{{ __st('elora.flash.up_to', ['percent' => $upTo]) }}</span>@endif
                    </h2>
                    <div class="flex w-fit items-center gap-6 rounded-full bg-white px-9 py-2 text-elo-primary max-lg:px-5">
                        <x-ui::icon name="clock" :size="56" class="max-lg:size-8" />
                        <x-sf::elora.countdown :ends-at="$sale->endsAt" class="elo-countdown-plain whitespace-nowrap text-[clamp(28px,4.4vw,72px)] font-semibold" />
                    </div>
                    <a href="{{ $more }}" class="elo-ghost-btn mo-press">{{ __st('elora.home.explore_all') }}</a>
                </div>
                <x-sf::elora.spotlight :cards="$deals" layout="center" />
            </div>
        </section>
    @elseif ($style === 'tiles')
        <section class="elo-home-wrap mt-12 max-lg:mt-6 max-lg:px-0" aria-labelledby="elo-flash-title">
            <div class="elo-flash-panel rounded-2xl px-10 py-8 max-lg:rounded-none max-lg:px-4 max-lg:py-5">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <h2 id="elo-flash-title" class="flex items-center gap-3 text-3xl font-semibold max-lg:text-xl"><x-ui::icon name="zap" :size="32" />{{ __st('elora.flash.title') }}</h2>
                    <div class="flex items-center gap-3">
                        <span class="text-xl font-semibold text-[#ffd428] max-lg:text-sm">{{ __st('elora.flash.ends_in') }}</span>
                        <x-sf::elora.countdown :ends-at="$sale->endsAt" class="elo-countdown-outline" />
                        <a href="{{ $more }}" class="grid size-10 place-items-center" aria-label="{{ __st('elora.home.explore_all') }}"><x-ui::icon name="chevron-right" :size="28" class="rtl:-scale-x-100" /></a>
                    </div>
                </div>
                <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":16}'>
                    <div class="swiper-wrapper">
                        @foreach ($deals as $card)
                            <div class="swiper-slide !h-auto !w-[150px] lg:!w-[190px]" data-reveal="fade-up">
                                <a href="{{ $card->url }}" class="elo-flash-tile group">
                                    <span class="elo-flash-tile-img">
                                        @if ($card->image)<img src="{{ $card->image }}" alt="{{ $card->name }}" width="190" height="280" loading="lazy" decoding="async" class="size-full object-cover transition duration-700 group-hover:scale-105">@endif
                                        @if ($card->discount)<span class="elo-flash-chip">-{{ $card->discount }}%</span>@endif
                                    </span>
                                    <span class="grid gap-1 p-2">
                                        <span class="truncate text-sm text-sf-ink">{{ $card->name }}</span>
                                        <span class="flex items-baseline gap-1.5"><span class="text-lg font-bold text-elo-primary">{{ $card->price }}</span>@if ($card->compare)<span class="text-xs text-[#adadad] line-through">{{ $card->compare }}</span>@endif</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-6 flex justify-center"><a href="{{ $more }}" class="elo-ghost-btn mo-press">{{ __st('elora.home.explore_all') }}</a></div>
            </div>
        </section>
    @else
        <section class="elo-flash-panel mt-12 max-lg:mt-6" aria-labelledby="elo-flash-title">
            <div class="elo-home-wrap py-8">
                @if ($style === 'mixed')
                    <h2 id="elo-flash-title" class="mb-6 flex items-center justify-center gap-3 text-4xl font-semibold max-lg:text-2xl"><x-ui::icon name="zap" :size="36" class="text-[#ffd428]" />{{ __st('elora.flash.title') }}<x-ui::icon name="zap" :size="36" class="text-[#ffd428]" /></h2>
                    <div class="mb-6 flex items-center justify-center gap-3"><span class="elo-ends-vertical">{{ __st('elora.flash.ends_in') }}</span><x-sf::elora.countdown :ends-at="$sale->endsAt" class="elo-countdown-white" /></div>
                    <x-sf::elora.rail :cards="$deals" slide="!w-[180px] lg:!w-[207px]" />
                    <div class="mt-6 flex justify-center"><a href="{{ $more }}" class="elo-ghost-btn mo-press">{{ __st('elora.home.shop_now') }}</a></div>
                @else
                    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,220px)_minmax(0,1fr)] lg:items-center">
                        <div class="grid content-center gap-4 max-lg:justify-items-center max-lg:text-center" data-reveal="slide-start">
                            <span class="w-fit rounded-full bg-white/25 px-3 py-1 text-sm font-semibold">{{ __st('elora.flash.off', ['percent' => $upTo]) }}</span>
                            <h2 id="elo-flash-title" class="elo-flash-display">{{ __st('elora.flash.title') }}</h2>
                            <div class="flex items-center gap-3"><span class="elo-ends-vertical">{{ __st('elora.flash.ends_in') }}</span><x-sf::elora.countdown :ends-at="$sale->endsAt" class="elo-countdown-outline" /></div>
                        </div>
                        <x-sf::elora.wide-grid :cards="$deals" :rows="2" />
                    </div>
                    <div class="mt-6 flex justify-center"><a href="{{ $more }}" class="elo-white-btn mo-press">{{ __st('elora.home.shop_now') }}</a></div>
                @endif
            </div>
        </section>
    @endif
@endif
