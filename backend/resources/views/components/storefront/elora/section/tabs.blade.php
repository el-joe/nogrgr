{{--
    Elora v1 product tabs (1:15432 under the trust strip): pill tabs — Flash products ·
    Top-rated products · Best sellers · Offers · New in — each a swipeable product rail with
    "Show More" to its listing. Tabs without products are left out. Tab switching:
    resources/js/storefront/elora/home.js ([data-elo-tabs]); arrows / Home / End move focus.
--}}
@props(['rails' => []])
@php
    $tabs = array_values(array_filter([
        ['key' => 'flash', 'icon' => 'zap', 'cards' => $rails['deals'] ?? [], 'more' => route('storefront.offers', ['flash' => 1])],
        ['key' => 'top_rated', 'icon' => 'star', 'cards' => $rails['topRated'] ?? [], 'more' => route('storefront.top-rated')],
        ['key' => 'best', 'icon' => 'award', 'cards' => $rails['best'] ?? [], 'more' => route('storefront.best-sellers')],
        ['key' => 'offers', 'icon' => 'percent', 'cards' => $rails['offers'] ?? [], 'more' => route('storefront.offers')],
        ['key' => 'new', 'icon' => 'sparkles', 'cards' => $rails['newRail'] ?? [], 'more' => route('storefront.new-in')],
    ], static fn (array $tab): bool => $tab['cards'] !== []));
@endphp
@if ($tabs !== [])
    <section class="elo-home-wrap mt-10 grid gap-6 max-lg:mt-6" data-elo-tabs aria-label="{{ __st('elora.tabs.label') }}">
        <div class="flex gap-4 overflow-x-auto pb-1 [scrollbar-width:none] lg:justify-center" role="tablist">
            @foreach ($tabs as $index => $tab)
                <button type="button" role="tab" class="elo-tab mo-press" id="elo-tab-{{ $tab['key'] }}" aria-controls="elo-panel-{{ $tab['key'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? 0 : -1 }}">
                    <x-ui::icon :name="$tab['icon']" :size="20" />{{ __st('elora.tabs.'.$tab['key']) }}
                </button>
            @endforeach
        </div>
        @foreach ($tabs as $index => $tab)
            <div role="tabpanel" id="elo-panel-{{ $tab['key'] }}" aria-labelledby="elo-tab-{{ $tab['key'] }}" class="grid justify-items-center gap-8" @if ($index > 0) hidden @endif>
                <x-sf::elora.rail :cards="$tab['cards']" class="w-full" />
                <a href="{{ $tab['more'] }}" class="elo-outline-btn mo-press">{{ __st('elora.home.show_more') }}<x-ui::icon name="chevron-down" :size="20" /></a>
            </div>
        @endforeach
    </section>
@endif
