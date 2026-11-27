{{--
    "Explore your interests" (Ecommet 1:1110): small red promo line, title, swipeable
    category chips (Recommended + store categories) switching the 5-column grid via the
    home.tab fragment (no See More button). Desktop / tablet layout of the home page.
--}}
@props(['tabs' => [], 'result', 'kicker' => null])
{{-- Always shown, as on the old Ecommet home: an empty-state line replaces the grid without products. --}}
    <section {{ $attributes->class(['grid gap-6']) }} aria-labelledby="sf-interests-title" data-home-tabs>
        <div class="grid gap-1 text-center" data-reveal="fade-up">
            @if ($kicker)<p class="text-2xl font-medium leading-[30px] text-eco-red">{{ $kicker }}</p>@endif
            <h2 id="sf-interests-title" class="text-2xl font-medium uppercase leading-[30px] text-eco-black">{{ __st('catalog.home.explore') }}</h2>
        </div>
        @if (count($tabs) > 1)
            <div class="relative" data-reveal="fade-up">
                <div class="swiper" data-rail='{"spaceBetween":16,"freeMode":{"enabled":true,"sticky":false}}'>
                    <div class="swiper-wrapper p-1" role="tablist" aria-label="{{ __st('catalog.home.explore') }}">
                        @foreach ($tabs as $index => $tab)
                            <div class="swiper-slide !w-auto">
                                <button type="button" role="tab" class="eco-cat-chip" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="sf-interests-grid" data-tab-url="{{ route('storefront.home.tab', ['tab' => $tab['key']]) }}"><span class="line-clamp-2">{{ $tab['label'] }}</span></button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="absolute end-0 top-1/2 z-10 grid size-[62px] -translate-y-1/2 place-items-center bg-gradient-to-l from-white via-white to-transparent text-eco-black disabled:hidden rtl:bg-gradient-to-r" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="32" class="rtl:-scale-x-100" /></button>
            </div>
        @endif
        <div id="sf-interests-grid" role="tabpanel" aria-live="polite" class="eco-card-grid lg:px-7" data-reveal-stagger>
            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards])
        </div>
        <p class="py-10 text-center text-sm text-eco-gray" data-interests-empty @unless ($result->isEmpty()) hidden @endunless>{{ __st('catalog.home.interests_empty') }}</p>
    </section>
