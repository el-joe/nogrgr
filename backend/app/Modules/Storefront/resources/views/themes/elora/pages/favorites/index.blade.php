{{--
    Elora favorites (Figma 1:18588 / m 1:23220; empty m 1:23277): breadcrumbs, "My
    Favorite" + "Search in Favorites…" pill (filters in place, `q` server-side too), the
    Elora product cards with a remove chip (storefront.favorites.toggle, card leaves), the
    no-match state and the broken-heart empty state.
    Inputs as the Ecommet view: $cards (ProductCardData), $total, $search, $seo.
--}}
<x-sf::layout :title="__st('elora_pages.favorites.title')" :mobile-title="__st('elora_pages.favorites.title')" nav="favorites" page="wishlist" :seo="$seo">
    <div class="elo-narrow grid content-start gap-5 pb-16 pt-4 lg:pt-6" data-sf-account>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="hidden lg:block">
            <ol class="elo-crumbs">
                <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li><span aria-current="page">{{ __st('shell.favorite') }}</span></li>
            </ol>
        </nav>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-medium text-elo-ink lg:text-xl">{{ __st('elora_pages.favorites.title') }}</h1>
            @if ($total > 0)
                <form method="GET" action="{{ route('storefront.favorites') }}" role="search" class="flex h-10 w-full items-center gap-2 rounded-full border border-elo-line bg-white px-4 sm:w-[330px]" data-filter-search="wishlist">
                    <label for="elo-wishlist-q" class="sr-only">{{ __st('elora_pages.favorites.search') }}</label>
                    <input id="elo-wishlist-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('elora_pages.favorites.search') }}" autocomplete="off" class="h-full min-w-0 flex-1 bg-transparent text-xs text-elo-ink outline-none">
                    <x-ui::icon name="search" :size="18" class="shrink-0 text-sf-muted" />
                </form>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6" data-filter-list="wishlist" data-reveal-stagger>
            @foreach ($cards as $card)
                <div class="relative min-w-0 transition-[opacity,transform] duration-300 data-[removing]:scale-95 data-[removing]:opacity-0" data-reveal="fade-up" data-filter-item data-filter-text="{{ \Illuminate\Support\Str::lower($card->name) }}" data-wishlist-card>
                    <x-sf::product-card :card="$card" />
                    <button type="button" class="absolute start-2 top-2 z-10 grid size-7 place-items-center rounded-full bg-white/90 text-elo-ink shadow transition hover:bg-white hover:text-sf-danger" data-wishlist-remove="{{ $card->id }}" aria-label="{{ __st('account.wishlist.remove', ['name' => $card->name]) }}"><x-ui::icon name="x" :size="14" /></button>
                </div>
            @endforeach
        </div>

        @if ($total > 0)
            <div class="elo-empty" data-filter-empty data-filter-term-title="{{ __st('account.wishlist.no_results', ['term' => ':term']) }}" @if ($cards !== []) hidden @endif>
                <span class="elo-empty-icon"><x-ui::icon name="search" :size="40" /></span>
                <p class="text-base font-medium text-elo-ink">{{ __st('account.wishlist.no_results', ['term' => $search ?? '']) }}</p>
            </div>
        @endif
        <div class="elo-empty min-h-[50vh] content-center" data-wishlist-empty @if ($total > 0) hidden @endif data-reveal="fade-up">
            <span class="elo-empty-icon"><x-ui::icon name="heart" :size="44" /></span>
            <h2>{{ __st('elora_pages.favorites.empty_title') }}</h2>
            <p>{{ __st('elora_pages.favorites.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="elo-btn elo-btn-ink mo-press mt-2 px-7 text-base">{{ __st('elora_pages.cart.continue') }}<x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" /></a>
        </div>
    </div>
</x-sf::layout>
