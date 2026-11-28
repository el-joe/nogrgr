{{--
    Elora v1 flash sale (1:15595, m 1:21252): the sale artwork (the sale's own banner, else
    the theme's), the gradient band with the h / m / s boxes, the sale name, the "Use Code"
    chip (page-builder setting; copies the code) and the deal rail. Hidden without a live sale.
--}}
@props(['sale' => null, 'deals' => [], 'code' => null, 'delivery' => null])
@if ($sale && $deals !== [])
    @php
        $more = route('storefront.offers', ['flash' => 1]);
        $title = $sale->title !== '' ? $sale->title : __st('elora.flash.title');
        $banner = $sale->bannerUrl ?: \Illuminate\Support\Facades\Vite::asset('resources/images/elora/flash-sale.webp');
    @endphp
    <section {{ $attributes->class(['v1-flash']) }} aria-labelledby="v1-flash-title">
        <h2 id="v1-flash-title" class="sr-only">{{ __st('elora.flash.title') }}</h2>
        <a href="{{ $more }}" class="v1-flash-banner">
            <img src="{{ $banner }}" alt="{{ __st('elora.flash.title') }} — {{ $title }}" width="2880" height="302" loading="lazy" decoding="async" class="h-full w-full object-contain">
        </a>
        <div class="v1-flash-band">
            <div class="v1-flash-top">
                {{-- Days box only for sales ending in more than 99 hours; otherwise h / m / s as designed. --}}
                @php($units = ($sale->endsAt->getTimestamp() - time() > 359999 ? ['days' => 'd'] : []) + ['hours' => 'h', 'minutes' => 'm', 'seconds' => 's'])
                <p class="v1-countdown" dir="ltr" data-countdown="{{ $sale->endsAt->toIso8601String() }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
                    @foreach ($units as $unit => $suffix)
                        <span class="v1-countdown-box"><span data-unit="{{ $unit }}">00</span>{{ __st('elora.flash.'.$suffix) }}</span>
                    @endforeach
                </p>
                <div class="v1-flash-side">
                    <p class="v1-flash-caption">{{ $title }}</p>
                    @if ($code)
                        <button type="button" class="v1-code mo-press" data-copy-code="{{ $code }}">{{ __st('elora.home.use_code', ['code' => '']) }}<span dir="ltr">{{ $code }}</span></button>
                    @endif
                </div>
            </div>
            <x-sf::elora.v1.rail :cards="$deals" kind="flash" :delivery="$delivery" :label="__st('elora.flash.title')" />
        </div>
    </section>
@endif
