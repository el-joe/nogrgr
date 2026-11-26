{{--
    Elora family copy (same markup / hooks, Elora tokens).
    Themed 404 (SF-ECO-036, Storefront additions 114:108479 / m 114:108666): big red code,
    "This page doesn't exist", search box, Back to home / Continue shopping, and a
    "You may like" row of best sellers (skipped when the catalog cannot be read).
--}}
@php
    $cards = [];

    try {
        $entries = app(\App\Modules\Storefront\Services\Catalog\ProductListing::class)->rail(\App\Modules\Storefront\Enums\ListingKind::BestSelling, 4);
        $cards = app(\App\Modules\Storefront\Services\Catalog\ProductCards::class)->make($entries);
    } catch (\Throwable) {
        $cards = [];
    }
@endphp
<x-sf::layout :title="__st('errors.404.title')" page="not-found">
    <div class="elo-wrap pb-16 pt-12 lg:pt-20">
        <div class="mx-auto grid max-w-[480px] justify-items-center gap-4 text-center" data-reveal="fade-up">
            <p class="text-[96px] font-semibold leading-none tracking-tight text-elo-primary lg:text-[128px]" aria-hidden="true">404</p>
            <h1 class="mt-4 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('errors.404.title') }}</h1>
            <p class="text-sm leading-6 text-sf-muted">{{ __st('errors.404.body') }}</p>
            <form action="{{ route('storefront.search') }}" method="GET" role="search" class="relative w-full">
                <label for="sf-404-search" class="sr-only">{{ __st('errors.404.search') }}</label>
                <x-ui::icon name="search" :size="18" class="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-sf-muted" />
                <input id="sf-404-search" type="search" name="q" placeholder="{{ __st('errors.404.search') }}" class="h-11 w-full rounded-lg border border-sf-border bg-white pe-4 ps-11 text-sm text-sf-ink outline-none focus:border-sf-ink">
            </form>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('storefront.home') }}" class="sf-btn sf-btn-cta mo-press rounded-full px-6">{{ __st('errors.back_home') }}</a>
                <a href="{{ route('storefront.best-sellers') }}" class="sf-btn sf-btn-outline mo-press rounded-full px-6">{{ __st('errors.404.continue') }}</a>
            </div>
        </div>

        @if ($cards !== [])
            <section class="mt-16 grid gap-4" aria-labelledby="sf-404-picks">
                <h2 id="sf-404-picks" class="text-lg font-medium text-sf-ink" data-reveal="fade-up">{{ __st('errors.404.you_may_like') }}</h2>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" data-reveal-stagger>
                    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $cards, 'variant' => 'grid'])
                </div>
            </section>
        @endif
    </div>
</x-sf::layout>
