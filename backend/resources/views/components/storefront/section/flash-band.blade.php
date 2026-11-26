{{--
    Flash-sale countdown band (Ecommet 1:1154 / m 1:2656): pink band, title, flipping
    countdown to the end of the live flash sale that ends first (FlashSaleCatalog), sale
    name + "Shop the sale". Hidden without a live sale in the shopping country.
--}}
@props(['sale' => null])
@if ($sale)
    <section {{ $attributes->class(['eco-flash-band']) }} aria-labelledby="sf-flash-title" data-reveal="fade">
        <div class="mx-auto flex max-w-[1210px] flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-6 lg:min-h-[180px] lg:flex-nowrap lg:px-0">
            <div class="grid gap-1 lg:contents">
                <h2 id="sf-flash-title" class="text-lg font-medium text-eco-black lg:text-2xl">{{ __st('catalog.home.flash_now') }}</h2>
                <p class="eco-countdown text-xl text-eco-red lg:text-[40px] lg:text-eco-black" data-countdown="{{ $sale->endsAt->toIso8601String() }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
                    <span data-unit="days">00</span><span aria-hidden="true">:</span><span data-unit="hours">00</span><span aria-hidden="true">:</span><span data-unit="minutes">00</span><span aria-hidden="true">:</span><span data-unit="seconds">00</span>
                </p>
            </div>
            <div class="grid justify-items-center gap-2 lg:flex lg:w-[362px] lg:items-center lg:justify-between lg:gap-6">
                <p class="max-w-[160px] text-center text-sm text-eco-black lg:max-w-[150px] lg:text-start lg:text-base">{{ $sale->title !== '' ? $sale->title : __st('catalog.home.flash_save', ['percent' => (int) $sale->percentage]) }}</p>
                <a href="{{ route('storefront.offers', ['flash' => 1]) }}" class="eco-pill-dark h-9 px-4 text-sm lg:h-12 lg:pe-6 lg:ps-8 lg:text-base">{{ __st('catalog.home.flash_cta', ['percent' => (int) $sale->percentage]) }}</a>
            </div>
        </div>
    </section>
@endif
