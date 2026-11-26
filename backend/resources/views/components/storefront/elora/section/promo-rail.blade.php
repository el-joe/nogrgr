{{--
    Elora v1 last rail (1:15432 bottom): orange strip "Free shipping when you purchase …" with
    "See all" at the start, then a product rail (offers, else top rated). The strip text is a
    store static text (elora.home.promo_strip).
--}}
@props(['cards' => []])
@if ($cards !== [])
    <section class="mt-12 max-lg:mt-6" aria-label="{{ __st('elora.home.promo_strip') }}">
        <div class="elo-orange-band">
            <div class="elo-home-wrap relative flex h-10 items-center justify-center text-sm text-white">
                <a href="{{ route('storefront.offers') }}" class="absolute start-4 inline-flex items-center gap-1 text-base underline lg:start-14"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />{{ __st('elora.home.see_all') }}</a>
                <p class="max-lg:ps-20">{{ __st('elora.home.promo_strip') }}</p>
            </div>
        </div>
        <div class="elo-home-wrap mt-6"><x-sf::elora.rail :cards="$cards" /></div>
    </section>
@endif
