{{--
    Elora favorites (client Figma 1:18588 / m 1:23220; empty m 1:23277): breadcrumbs, "My
    Favorite" + "Search in Favorites…" pill (filters in place, `q` server-side too), the
    Elora product cards (shared x-sf::elora.v1.card sizes) whose heart removes the product
    (storefront.favorites.toggle, card leaves), the no-match and the empty state. Phones and
    tablets also get the three rails of the phone frame (same rail component and steps as the
    cart and product page; saved products are skipped).
    Inputs as the Ecommet view: $cards (ProductCardData), $total, $search, $seo.
--}}
@php($rails = app(\App\Modules\Storefront\Services\Catalog\HomeService::class)->cartRails(array_map(static fn ($card): int => (int) $card->id, $cards)))
<x-sf::layout :title="__st('elora_pages.favorites.title')" nav="favorites" page="wishlist" :seo="$seo">
    <div class="eac eac-fav" data-elo-account="wishlist" data-sf-account>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="eac-crumbs">
            <ol>
                <li><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('shell.favorite') }}</span></li>
            </ol>
        </nav>

        <div class="eac-fav-head">
            <h1><span class="max-lg:hidden">{{ __st('elora_pages.favorites.title') }}</span><span class="lg:hidden">{{ __st('elora_pages.favorites_mobile.title') }}</span></h1>
            @if ($total > 0)
                <form method="GET" action="{{ route('storefront.favorites') }}" role="search" class="eac-search" data-filter-search="wishlist">
                    <label for="elo-wishlist-q" class="sr-only">{{ __st('elora_pages.favorites.search') }}</label>
                    <input id="elo-wishlist-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('elora_pages.favorites.search') }}" autocomplete="off">
                    <button type="submit" aria-label="{{ __st('elora_pages.favorites.search') }}"><x-ui::icon name="elo-search-lg" :size="24" /></button>
                </form>
            @endif
        </div>

        <div class="eac-fav-body">
            <div class="eac-fav-grid v1-home" data-filter-list="wishlist" data-reveal-stagger>
                @foreach ($cards as $card)
                    <div class="eac-fav-item" data-reveal="fade-up" data-filter-item data-filter-text="{{ \Illuminate\Support\Str::lower($card->name) }}" data-wishlist-card>
                        <x-sf::product-card :card="$card" />
                        <button type="button" class="eac-fav-remove mo-press" data-wishlist-remove="{{ $card->id }}" aria-label="{{ __st('account.wishlist.remove', ['name' => $card->name]) }}"><x-ui::icon name="elo-heart" :size="20" /></button>
                    </div>
                @endforeach
            </div>

            @if ($total > 0)
                <div class="elo-empty eac-empty" data-filter-empty data-filter-term-title="{{ __st('account.wishlist.no_results', ['term' => ':term']) }}" @if ($cards !== []) hidden @endif>
                    <span class="elo-empty-icon"><x-ui::icon name="search" :size="40" /></span>
                    <p>{{ __st('account.wishlist.no_results', ['term' => $search ?? '']) }}</p>
                </div>
            @endif
            <div class="elo-empty eac-empty" data-wishlist-empty @if ($total > 0) hidden @endif data-reveal="fade-up">
                <span class="elo-empty-icon"><x-ui::icon name="heart" :size="40" /></span>
                <h2>{{ __st('elora_pages.favorites.empty_title') }}</h2>
                <p>{{ __st('elora_pages.favorites.empty_body') }}</p>
                <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink mo-press mt-2 px-6">{{ __st('elora_pages.cart.continue') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
            </div>
        </div>

        @if (collect($rails)->flatten()->isNotEmpty())
            <div class="epd epd-rails eac-fav-rails v1-home lg:hidden">
                <x-sf::elora.v1.pdp-rail :cards="$rails['similar']" :title="__st('elora_pages.product.similar')" :more="route('storefront.best-sellers')" />
                <x-sf::elora.v1.pdp-rail :cards="$rails['viewed']" :title="__st('elora_pages.product.also_viewed')" :more="route('storefront.top-rated')" />
                <x-sf::elora.v1.pdp-rail :cards="$rails['recommended']" :title="__st('elora_pages.product.recommended')" :more="route('storefront.new-in')" />
            </div>
        @endif
    </div>
</x-sf::layout>
