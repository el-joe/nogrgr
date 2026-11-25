{{--
    Select markets modal body (VEN-015a, Tenant Panel Layout 12:14756) and its
    already-added state (VEN-015b, 12:14923): all countries toggle, selected country chips
    each with placements (trending / flash sale / new in / category), searchable country
    list, Save. Already added: markets + placements read-only with "Edit markets".
    Data: $product, $countries (store countries allowed for it), $markets (country id => StoreProductMarket), $editing, $canManage.
--}}
@php
    $placements = \App\Modules\Catalog\Enums\MarketPlacement::cases();
@endphp
<div class="grid gap-5" data-markets data-modal-title="{{ __('catalog::vendor.opportunities.markets_title') }}" data-modal-subtitle="{{ $product->name }}">
    @if ($countries->isEmpty())
        <x-ui::empty-state compact icon="globe" :description="__('catalog::vendor.products.no_countries')" />
    @elseif (! $editing)
        <div class="grid gap-1">
            <h3 class="text-lg font-semibold text-ink">{{ __('catalog::vendor.opportunities.added_in') }}</h3>
            <p class="text-sm text-ink-secondary">{{ __('catalog::vendor.opportunities.added_in_hint') }}</p>
        </div>
        <ul class="grid gap-4">
            @foreach ($markets as $countryId => $market)
                @php($country = $countries->firstWhere('id', $countryId))
                <li class="grid gap-2">
                    <span class="w-fit rounded-full bg-primary-subtle px-3 py-1 text-sm font-medium text-ink">{{ $country?->name ?? '#'.$countryId }}</span>
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        @foreach ($placements as $placement)
                            <span @class(['inline-flex items-center gap-2', 'font-medium text-ink-brand' => $market->has($placement), 'text-ink-muted' => ! $market->has($placement)])>
                                <x-ui::icon :name="$market->has($placement) ? 'check-circle' : 'minus'" :size="18" />{{ $placement->label() }}
                            </span>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="grid gap-3 sm:grid-cols-2">
            <a href="{{ vendor_route('catalog.products.index') }}" class="vnd-card-cta">{{ __('catalog::vendor.opportunities.go_products') }}</a>
            @if ($canManage)
                <button type="button" class="vnd-cta vnd-cta-outline h-14 rounded-[12px]" data-remote-modal="markets-modal" data-url="{{ vendor_route('catalog.opportunities.markets', ['central' => $product->id, 'edit' => 1]) }}">{{ __('catalog::vendor.opportunities.edit_markets') }}</button>
            @endif
        </div>
    @else
        <form method="POST" action="{{ vendor_route('catalog.opportunities.markets.store', ['central' => $product->id]) }}" class="grid gap-5" data-ajax data-markets-form novalidate>
            @csrf
            <x-ui::checkbox name="all_countries" :label="__('catalog::vendor.opportunities.all_countries')" data-markets-all />
            <div class="grid gap-3">
                <h3 class="text-lg font-semibold text-ink">{{ __('catalog::vendor.opportunities.select_countries') }}</h3>
                <div class="grid gap-3" data-markets-selected>
                    @foreach ($countries as $country)
                        @php($market = $markets->get($country->id))
                        <fieldset class="grid gap-2" data-market-row="{{ $country->id }}" @if (! $market) hidden @endif>
                            <legend class="inline-flex w-fit items-center gap-1 rounded-full bg-primary-subtle px-3 py-1 text-sm text-ink">{{ $country->name }}
                                <button type="button" class="text-danger" data-market-remove="{{ $country->id }}" aria-label="{{ __('catalog::vendor.opportunities.remove_country', ['country' => $country->name]) }}"><x-ui::icon name="x-circle" :size="14" /></button>
                            </legend>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                @foreach ($placements as $placement)
                                    <x-ui::checkbox :name="'placements['.$country->id.'][]'" :value="$placement->value" :label="$placement->label()" :checked="$market?->has($placement) ?? false" />
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <div class="ui-control min-h-10">
                    <x-ui::icon name="search" :size="18" class="text-ink-muted" />
                    <label for="markets-search-{{ $product->id }}" class="sr-only">{{ __('catalog::vendor.opportunities.search_countries') }}</label>
                    <input id="markets-search-{{ $product->id }}" type="search" class="h-9" placeholder="{{ __('catalog::vendor.opportunities.search_countries') }}" data-markets-search autocomplete="off">
                </div>
                <ul class="grid max-h-[220px] gap-1 overflow-y-auto rounded-card bg-subtle p-3" data-markets-list>
                    @foreach ($countries as $country)
                        <li data-market-option data-name="{{ \Illuminate\Support\Str::lower($country->name.' '.$country->iso2) }}">
                            <x-ui::checkbox name="countries[]" :value="$country->id" :label="trim($country->flag.' '.$country->name)" :checked="$markets->has($country->id)" data-market-country />
                        </li>
                    @endforeach
                    <li data-markets-none hidden class="py-2 text-sm text-ink-muted">{{ __('catalog::vendor.opportunities.no_country_match') }}</li>
                </ul>
            </div>
            @if ($canManage)
                <button type="submit" class="vnd-card-cta sm:w-[177px]">{{ __('catalog::vendor.save') }}</button>
            @endif
        </form>
    @endif
</div>
