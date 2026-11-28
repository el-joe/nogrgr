{{--
    Elora v1 last rail (1:15822 / 1:15828, desktop): orange strip ("Free shipping when you
    purchase …" from the shipping rules, else a deals line) with "See all", then a rail.
--}}
@props(['cards' => [], 'weight' => null, 'always' => false, 'delivery' => null])
@if ($cards !== [])
    @php($text = $always ? __st('shell.free_shipping.title') : ($weight ? __st('elora.home.free_shipping_weight', ['weight' => $weight]) : __st('elora.home.promo_strip')))
    <section {{ $attributes->class(['v1-promo']) }} aria-label="{{ $text }}">
        <div class="v1-promo-strip v1-grad-bg">
            <a href="{{ route('storefront.offers') }}" class="v1-promo-all"><span class="v1-ico v1-ico-arrow-white" aria-hidden="true"></span><span>{{ __st('elora.home.see_all_link') }}</span></a>
            <p>{{ $text }}</p>
        </div>
        <x-sf::elora.v1.rail :cards="$cards" :delivery="$delivery" data-reveal="fade-up" />
    </section>
@endif
