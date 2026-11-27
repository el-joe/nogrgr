{{--
    Lightning deals / Clearance deals (Ecommet 1:1046, m 1:2656): two tabs over swipeable
    deal-card rails (flash progress + countdown). A tab without products is left out; the
    section is hidden when both are empty.
--}}
@props(['deals' => [], 'clearance' => []])
@php
    $tabs = array_values(array_filter([
        $deals !== [] ? ['key' => 'lightning', 'label' => __st('catalog.home.lightning_deals'), 'icon' => 'falling-star', 'cards' => $deals, 'more' => route('storefront.offers', ['flash' => 1]), 'more_label' => __st('catalog.home.limited_time')] : null,
        $clearance !== [] ? ['key' => 'clearance', 'label' => __st('catalog.home.clearance_deals'), 'icon' => 'flame', 'cards' => $clearance, 'more' => route('storefront.offers', ['sort' => 'discount']), 'more_label' => __st('catalog.home.limited_stock')] : null,
    ]));
@endphp
@if ($tabs !== [])
    <section {{ $attributes->class(['grid grid-cols-1 gap-3 max-lg:overflow-x-clip lg:gap-4']) }} data-simple-tabs data-reveal="fade-up" aria-label="{{ __st('catalog.home.deals_label') }}">
        <div class="flex items-center justify-between gap-4" role="tablist">
            @foreach ($tabs as $index => $tab)
                @if ($index === 1)<a href="{{ $tabs[0]['more'] }}" class="hidden items-center gap-0.5 text-sm text-eco-black hover:underline lg:inline-flex">{{ $tabs[0]['more_label'] }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>@endif
                <button type="button" role="tab" id="sf-deals-tab-{{ $tab['key'] }}" aria-controls="sf-deals-{{ $tab['key'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" @class(['eco-deals-title inline-flex items-center gap-2 transition-opacity aria-[selected=false]:opacity-60 hover:opacity-100', 'max-lg:hidden' => $index > 0]) @if (count($tabs) === 1) tabindex="-1" @endif>
                    <x-ui::icon :name="$tab['icon']" :size="28" />{{ $tab['label'] }}
                </button>
            @endforeach
            <a href="{{ $tabs[count($tabs) - 1]['more'] }}" class="hidden items-center gap-0.5 text-sm text-eco-black hover:underline lg:inline-flex">{{ $tabs[count($tabs) - 1]['more_label'] }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
        </div>
        @foreach ($tabs as $index => $tab)
            <div id="sf-deals-{{ $tab['key'] }}" role="tabpanel" aria-labelledby="sf-deals-tab-{{ $tab['key'] }}"  @if ($index !== 0) hidden @endif>
                <x-sf::section.rail :cards="$tab['cards']" variant="deal" :label="$tab['label']" />
            </div>
        @endforeach
    </section>
@endif
