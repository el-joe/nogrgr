{{--
    Elora v1 product tabs (1:15554 + 1:15586, m 1:21215): Flash products · Top-rated · Fast
    delivery (in stock) · Coupon deals · New in. The pill row stays put; each panel is its own
    Swiper rail clipped to the page container. No "Show More" (client). Tab switching:
    resources/js/storefront/elora/home.js ([data-elo-tabs]). Tabs without products are left out.
--}}
@props(['rails' => [], 'delivery' => null])
@php
    $tabs = array_values(array_filter([
        ['key' => 'flash', 'icon' => 'zap', 'cards' => $rails['deals'] ?? []],
        ['key' => 'top_rated', 'icon' => 'star', 'cards' => $rails['topRated'] ?? [], 'short' => 'top_rated_short'],
        ['key' => 'fast', 'icon' => 'truck', 'cards' => $rails['fast'] ?? []],
        ['key' => 'offers', 'icon' => 'ticket', 'cards' => $rails['offers'] ?? []],
        ['key' => 'new', 'icon' => 'sparkles', 'cards' => $rails['newRail'] ?? []],
    ], static fn (array $tab): bool => $tab['cards'] !== []));
@endphp
@if ($tabs !== [])
    <section {{ $attributes->class(['v1-tabs']) }} data-elo-tabs aria-label="{{ __st('elora.tabs.label') }}">
        <div class="v1-tablist" role="tablist">
            @foreach ($tabs as $index => $tab)
                <button type="button" role="tab" class="v1-tab v1-tab-{{ $tab['key'] }} mo-press" id="elo-tab-{{ $tab['key'] }}" aria-controls="elo-panel-{{ $tab['key'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? 0 : -1 }}">
                    <span class="v1-ico v1-ico-{{ $tab['icon'] }}" aria-hidden="true"></span>
                    @if (isset($tab['short']))
                        <span class="lg:hidden">{{ __st('elora.tabs.'.$tab['short']) }}</span><span class="max-lg:hidden">{{ __st('elora.tabs.'.$tab['key']) }}</span>
                    @else
                        <span>{{ __st('elora.tabs.'.$tab['key']) }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        @foreach ($tabs as $index => $tab)
            <div role="tabpanel" id="elo-panel-{{ $tab['key'] }}" aria-labelledby="elo-tab-{{ $tab['key'] }}" class="v1-tabpanel" @if ($index > 0) hidden @endif>
                <x-sf::elora.v1.rail :cards="$tab['cards']" kind="tabs" :delivery="$delivery" />
            </div>
        @endforeach
    </section>
@endif
