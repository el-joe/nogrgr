{{--
    Elora cart rails (Figma 1:17541 / m 1:22029): the phone "People buy with" pair and the rails,
    the same Swiper component, steps and card sizes as the product page (x-sf::elora.v1.pdp-rail
    inside .epd-rails.v1-home). $rails = HomeService::cartRails() (products in the cart are skipped).
--}}
@props(['rails' => []])
@if (collect($rails)->flatten()->isNotEmpty())
    <div class="epd epd-rails epd-rails-cart v1-home">
        @if (count($rails['together']) === 2)
            <section class="elo-cart-bundle lg:hidden" aria-labelledby="elo-cart-bundle-title" data-reveal="fade-up">
                <h2 id="elo-cart-bundle-title">{{ __st('elora_pages.cart.people_buy_with') }}</h2>
                <div class="elo-cart-bundle-row">
                    <x-sf::elora.v1.card :card="$rails['together'][0]" :eager="true" />
                    <span class="v1-ico v1-ico-plus" aria-hidden="true"></span>
                    <x-sf::elora.v1.card :card="$rails['together'][1]" :eager="true" />
                </div>
            </section>
        @endif
        <div class="elo-cart-rail elo-cart-rail-similar">
            <x-sf::elora.v1.pdp-rail :cards="$rails['similar']" :title="__st('elora_pages.product.similar')" :more="route('storefront.best-sellers')" />
        </div>
        <div class="elo-cart-rail elo-cart-rail-viewed">
            <x-sf::elora.v1.pdp-rail :cards="$rails['viewed']" :title="__st('elora_pages.product.also_viewed')" :more="route('storefront.top-rated')" />
        </div>
        <div class="elo-cart-rail elo-cart-rail-recommended">
            <x-sf::elora.v1.pdp-rail :cards="$rails['recommended']" :title="__st('elora_pages.product.recommended')" :desktop-title="__st('elora_pages.cart.recommended')" :more="route('storefront.new-in')" />
        </div>
    </div>
@endif
