{{--
    Phone home product tabs (m 1:2656 / alt 1:2710, tab states board 1:4261): Best-Selling
    Items · 5-Star Rated · New In, swipeable between tabs (tabs-swipe), 2-column grids,
    "See More" per tab. All tabs of the design stay; an empty one shows its message and the
    section is only left out when every tab is empty.
    $tabs: list of [key, label, icon, result, more, ribbon, tag, empty]
--}}
@props(['tabs' => []])
@if (array_filter($tabs, fn ($tab) => ! $tab['result']->isEmpty()) !== [])
    <section {{ $attributes->class(['grid gap-3']) }} data-tabs-swipe aria-label="{{ __st('catalog.home.products_label') }}">
        <div class="flex justify-between gap-4 overflow-x-auto px-4 [scrollbar-width:none]" role="tablist">
            @foreach ($tabs as $index => $tab)
                <button type="button" role="tab" class="eco-tab eco-tab-red eco-tab-short gap-1 text-sm font-normal tracking-[0.5px] [--eco-tab-line:71px]" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? 0 : -1 }}" aria-controls="sf-mtab-{{ $tab['key'] }}" id="sf-mtab-btn-{{ $tab['key'] }}">
                    <x-ui::icon :name="$tab['icon']" :size="16" />{{ $tab['label'] }}
                </button>
            @endforeach
        </div>
        <div class="swiper">
            <div class="swiper-wrapper items-start">
                @foreach ($tabs as $tab)
                    <div class="swiper-slide grid gap-6 px-4" role="tabpanel" id="sf-mtab-{{ $tab['key'] }}" aria-labelledby="sf-mtab-btn-{{ $tab['key'] }}">
                        <div id="sf-mtab-grid-{{ $tab['key'] }}" class="eco-card-grid gap-y-4">
                            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $tab['result']->cards, 'ribbon' => $tab['ribbon'] ?? false, 'tag' => $tab['tag'] ?? null])
                        </div>
                        @if ($tab['result']->isEmpty())
                            <p class="py-10 text-center text-sm text-eco-gray">{{ $tab['empty'] }}</p>
                        @endif
                        <x-sf::section.load-more :url="$tab['result']->hasMore() ? $tab['more'] : null" :target="'#sf-mtab-grid-'.$tab['key']" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
