{{--
    Filter panel (Ecommet 1:1796): accordions per facet, Reset / "Show N results". A GET
    form on the listing URL (works without JS); side drawer on desktop, bottom sheet on phones.
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingQuery $query */
    $facets = $result->facets;
    $kind = $query->kind;
    $price = $query->priceMin !== null || $query->priceMax !== null ? ($query->priceMin ?? '').'-'.($query->priceMax ?? '') : '';
    $keep = array_filter(['q' => $query->term, 'sort' => $query->sort !== $kind->defaultSort() ? $query->sort->value : null, 'period' => $kind === \App\Modules\Storefront\Enums\ListingKind::BestSelling && $query->period?->value !== 30 ? $query->period?->value : null]);
@endphp
<x-sf::modal id="sf-filters" :title="__st('catalog.filters.title')" size="sm" data-sheet-mode="drawer" class="flex flex-col">
    <form method="GET" action="{{ url()->current() }}" class="grid gap-1" data-filter-form>
        @foreach ($keep as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        @if ($facets['categories'] !== [])
            <details class="eco-accordion border-t border-eco-stroke" @if ($query->categoryIds) open @endif>
                <summary>{{ __st('catalog.filters.category') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    @foreach ($facets['categories'] as $option)
                        <label class="eco-check"><input type="checkbox" name="cat[]" value="{{ $option['id'] }}" @checked(in_array($option['id'], $query->categoryIds, true))><span class="flex-1">{{ $option['name'] }}</span><span class="text-xs text-eco-gray">{{ $option['count'] }}</span></label>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($facets['price'] !== [])
            <details class="eco-accordion border-t border-eco-stroke" @if ($price !== '') open @endif>
                <summary>{{ __st('catalog.filters.price') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    <label class="eco-check"><input type="radio" name="price" value="" @checked($price === '')><span class="flex-1">{{ __st('catalog.filters.any') }}</span></label>
                    @foreach ($facets['price'] as $option)
                        <label class="eco-check"><input type="radio" name="price" value="{{ $option['value'] }}" @checked($price === $option['value'])><span class="flex-1">{{ $option['label'] }}</span><span class="text-xs text-eco-gray">{{ $option['count'] }}</span></label>
                    @endforeach
                </div>
            </details>
        @endif

        @foreach ($facets['options'] as $attribute)
            <details class="eco-accordion border-t border-eco-stroke" @if (isset($query->options[$attribute['id']])) open @endif>
                <summary>{{ $attribute['name'] }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid max-h-64 overflow-y-auto pb-3">
                    @foreach ($attribute['values'] as $option)
                        <label class="eco-check"><input type="checkbox" name="opt[{{ $attribute['id'] }}][]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $query->options[$attribute['id']] ?? [], true))><span class="flex-1">{{ $option['value'] }}</span><span class="text-xs text-eco-gray">{{ $option['count'] }}</span></label>
                    @endforeach
                </div>
            </details>
        @endforeach

        @if ($facets['rating'] !== [] && $kind !== \App\Modules\Storefront\Enums\ListingKind::TopRated)
            <details class="eco-accordion border-t border-eco-stroke" @if ($query->minRating) open @endif>
                <summary>{{ __st('catalog.filters.rating') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    <label class="eco-check"><input type="radio" name="rating" value="" @checked(! $query->minRating)><span>{{ __st('catalog.filters.any') }}</span></label>
                    @foreach ($facets['rating'] as $option)
                        <label class="eco-check"><input type="radio" name="rating" value="{{ $option['value'] }}" @checked($query->minRating === $option['value'])><x-sf::stars :rating="$option['value']" :size="14" /><span class="flex-1">{{ __st('catalog.filters.and_up') }}</span><span class="text-xs text-eco-gray">{{ $option['count'] }}</span></label>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($kind === \App\Modules\Storefront\Enums\ListingKind::TopRated)
            <details class="eco-accordion border-t border-eco-stroke" open>
                <summary>{{ __st('catalog.filters.rating') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    <label class="eco-check"><input type="radio" name="rating" value="" @checked(! $query->minRating)><x-sf::stars :rating="4" :size="14" /><span>{{ __st('catalog.filters.and_up') }}</span></label>
                    <label class="eco-check"><input type="radio" name="rating" value="5" @checked($query->minRating === 5)><x-sf::stars :rating="5" :size="14" /><span>{{ __st('catalog.filters.five_only') }}</span></label>
                </div>
            </details>
        @endif

        @if ($kind === \App\Modules\Storefront\Enums\ListingKind::NewIn)
            <details class="eco-accordion border-t border-eco-stroke" @if ($query->addedWithinDays) open @endif>
                <summary>{{ __st('catalog.filters.added') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    @foreach (['' => 'catalog.filters.any', '7' => 'catalog.filters.added_7', '30' => 'catalog.filters.added_30'] as $value => $label)
                        <label class="eco-check"><input type="radio" name="added" value="{{ $value }}" @checked((string) $query->addedWithinDays === (string) $value)><span>{{ __st($label) }}</span></label>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($kind === \App\Modules\Storefront\Enums\ListingKind::Offers)
            <details class="eco-accordion border-t border-eco-stroke" @if ($query->minDiscount || $query->flashOnly) open @endif>
                <summary>{{ __st('catalog.filters.discount') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
                <div class="grid pb-3">
                    <label class="eco-check"><input type="checkbox" name="flash" value="1" @checked($query->flashOnly)><span>{{ __st('catalog.filters.flash_only') }}</span></label>
                    @foreach ([10, 20, 30, 50] as $value)
                        <label class="eco-check"><input type="radio" name="discount" value="{{ $value }}" @checked($query->minDiscount === $value)><span>{{ __st('catalog.filters.discount_min', ['percent' => $value]) }}</span></label>
                    @endforeach
                </div>
            </details>
        @endif

        <details class="eco-accordion border-y border-eco-stroke" @if ($query->inStockOnly) open @endif>
            <summary>{{ __st('catalog.filters.availability') }}<x-ui::icon name="chevron-down" :size="22" /></summary>
            <div class="grid pb-3">
                <label class="eco-check"><input type="checkbox" name="in_stock" value="1" @checked($query->inStockOnly)><span class="flex-1">{{ __st('catalog.filters.in_stock') }}</span><span class="text-xs text-eco-gray">{{ $facets['in_stock'] }}</span></label>
            </div>
        </details>

        <div class="sticky bottom-0 mt-4 grid grid-cols-[auto_1fr] gap-4 bg-white pt-2">
            <a href="{{ url()->current().($keep ? '?'.http_build_query($keep) : '') }}" class="eco-pill-outline h-12 px-8" data-filter-reset>{{ __st('catalog.filters.reset') }}</a>
            <button type="submit" class="eco-pill-dark h-12">{{ __st('catalog.filters.show', ['count' => $result->total]) }}</button>
        </div>
    </form>
</x-sf::modal>
