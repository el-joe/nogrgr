{{--
    Souqify filter form (Figma 1:75534 sidebar · m 1:76311 sheet): accordion per facet (18px
    heading + chevron, 20px checkboxes; short values such as sizes in two columns) with a
    value search on long lists. A GET form on the listing URL (works without JS). The desktop
    sidebar ($mode = 'sidebar') applies each change at once (data-sq-autosubmit); the phone
    sheet ($mode = 'sheet') keeps "show (n result)" + "reset".
    Inputs: $query, $result, $mode.
--}}
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingQuery $query */
    $facets = $result->facets;
    $kind = $query->kind;
    $sheet = ($mode ?? 'sidebar') === 'sheet';
    $id = $sheet ? 'sq-fs' : 'sq-fb';
    $price = $query->priceMin !== null || $query->priceMax !== null ? ($query->priceMin ?? '').'-'.($query->priceMax ?? '') : '';
    $keep = array_filter(['q' => $query->term, 'sort' => $query->sort !== $kind->defaultSort() ? $query->sort->value : null, 'period' => $kind === \App\Modules\Storefront\Enums\ListingKind::BestSelling && $query->period?->value !== 30 ? $query->period?->value : null]);
    $resetUrl = url()->current().($keep ? '?'.http_build_query($keep) : '');
@endphp
<form method="GET" action="{{ url()->current() }}" @class(['grid', 'gap-1 px-4 pb-24' => $sheet, 'gap-6' => ! $sheet]) data-filter-form @unless ($sheet) data-sq-autosubmit @endunless>
    @foreach ($keep as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    @if ($facets['categories'] !== [])
        <details class="sq-acc" @if ($query->categoryIds || ! $sheet) open @endif>
            <summary>{{ __st('catalog.filters.category') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                @foreach ($facets['categories'] as $option)
                    <label class="sq-check"><input type="checkbox" name="cat[]" value="{{ $option['id'] }}" @checked(in_array($option['id'], $query->categoryIds, true))><span class="flex-1">{{ $option['name'] }}</span><span class="text-xs text-sf-muted">{{ $option['count'] }}</span></label>
                @endforeach
            </div>
        </details>
    @endif

    @foreach ($facets['options'] as $attribute)
        @php($many = count($attribute['values']) > 8)
        <details class="sq-acc" @if (isset($query->options[$attribute['id']]) || ($loop->first && ! $sheet)) open @endif data-sq-facet>
            <summary>{{ $attribute['name'] }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                @if ($many)
                    <label class="sr-only" for="{{ $id }}-s-{{ $attribute['id'] }}">{{ __st('souqify_pages.listing.facet_search', ['name' => $attribute['name']]) }}</label>
                    <input id="{{ $id }}-s-{{ $attribute['id'] }}" type="search" class="sq-facet-search" placeholder="{{ __st('souqify_pages.listing.facet_search', ['name' => $attribute['name']]) }}" data-sq-facet-search autocomplete="off">
                @endif
                @php($short = ! $many && collect($attribute['values'])->every(fn ($option) => mb_strlen((string) $option['value']) <= 4))
                <div @class(['grid max-h-64 overflow-y-auto', 'grid-cols-2 gap-x-6' => $short])>
                    @foreach ($attribute['values'] as $option)
                        <label class="sq-check" data-sq-facet-value="{{ \Illuminate\Support\Str::lower($option['value']) }}"><input type="checkbox" name="opt[{{ $attribute['id'] }}][]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $query->options[$attribute['id']] ?? [], true))><span class="flex-1">{{ $option['value'] }}</span><span class="text-xs text-sf-muted">{{ $option['count'] }}</span></label>
                    @endforeach
                </div>
                @if ($many)
                    <p class="text-xs text-sf-muted" data-sq-facet-empty hidden>{{ __st('souqify_pages.listing.facet_none') }}</p>
                @endif
            </div>
        </details>
    @endforeach

    @if ($facets['price'] !== [])
        <details class="sq-acc" @if ($price !== '') open @endif>
            <summary>{{ __st('catalog.filters.price') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                <label class="sq-check"><input type="radio" name="price" value="" @checked($price === '')><span class="flex-1">{{ __st('catalog.filters.any') }}</span></label>
                @foreach ($facets['price'] as $option)
                    <label class="sq-check"><input type="radio" name="price" value="{{ $option['value'] }}" @checked($price === $option['value'])><span class="flex-1">{{ $option['label'] }}</span><span class="text-xs text-sf-muted">{{ $option['count'] }}</span></label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($kind === \App\Modules\Storefront\Enums\ListingKind::TopRated)
        <details class="sq-acc" open>
            <summary>{{ __st('souqify_pages.listing.rating') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                <label class="sq-check"><input type="radio" name="rating" value="" @checked(! $query->minRating)><x-sf::stars :rating="4" :size="14" /><span>{{ __st('catalog.filters.and_up') }}</span></label>
                <label class="sq-check"><input type="radio" name="rating" value="5" @checked($query->minRating === 5)><x-sf::stars :rating="5" :size="14" /><span>{{ __st('catalog.filters.five_only') }}</span></label>
            </div>
        </details>
    @elseif ($facets['rating'] !== [])
        <details class="sq-acc" @if ($query->minRating) open @endif>
            <summary>{{ __st('souqify_pages.listing.rating') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                <label class="sq-check"><input type="radio" name="rating" value="" @checked(! $query->minRating)><span>{{ __st('catalog.filters.any') }}</span></label>
                @foreach ($facets['rating'] as $option)
                    <label class="sq-check"><input type="radio" name="rating" value="{{ $option['value'] }}" @checked($query->minRating === $option['value'])><x-sf::stars :rating="$option['value']" :size="14" /><span class="flex-1">{{ __st('catalog.filters.and_up') }}</span><span class="text-xs text-sf-muted">{{ $option['count'] }}</span></label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($kind === \App\Modules\Storefront\Enums\ListingKind::NewIn)
        <details class="sq-acc" @if ($query->addedWithinDays) open @endif>
            <summary>{{ __st('catalog.filters.added') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                @foreach (['' => 'catalog.filters.any', '7' => 'catalog.filters.added_7', '30' => 'catalog.filters.added_30'] as $value => $label)
                    <label class="sq-check"><input type="radio" name="added" value="{{ $value }}" @checked((string) $query->addedWithinDays === (string) $value)><span>{{ __st($label) }}</span></label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($kind === \App\Modules\Storefront\Enums\ListingKind::Offers)
        <details class="sq-acc" @if ($query->minDiscount || $query->flashOnly) open @endif>
            <summary>{{ __st('catalog.filters.discount') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
            <div class="grid gap-2 pb-2">
                <label class="sq-check"><input type="checkbox" name="flash" value="1" @checked($query->flashOnly)><span>{{ __st('catalog.filters.flash_only') }}</span></label>
                @foreach ([10, 20, 30, 50] as $value)
                    <label class="sq-check"><input type="radio" name="discount" value="{{ $value }}" @checked($query->minDiscount === $value)><span>{{ __st('catalog.filters.discount_min', ['percent' => $value]) }}</span></label>
                @endforeach
            </div>
        </details>
    @endif

    <details class="sq-acc" @if ($query->inStockOnly) open @endif>
        <summary>{{ __st('catalog.filters.availability') }}<x-ui::icon name="chevron-down" :size="18" /></summary>
        <div class="grid gap-2 pb-2">
            <label class="sq-check"><input type="checkbox" name="in_stock" value="1" @checked($query->inStockOnly)><span class="flex-1">{{ __st('catalog.filters.in_stock') }}</span><span class="text-xs text-sf-muted">{{ $facets['in_stock'] }}</span></label>
        </div>
    </details>

    @if ($sheet)
        <div class="fixed inset-x-0 bottom-0 z-10 grid grid-cols-[1fr_auto] gap-3 bg-white px-4 pb-[calc(12px+env(safe-area-inset-bottom))] pt-3 shadow-[0_-4px_12px_rgb(0_0_0/0.06)]">
            <button type="submit" class="sq-btn sq-btn-primary h-12 text-base">{{ __st('souqify_pages.listing.show_results', ['count' => $result->total]) }}</button>
            <a href="{{ $resetUrl }}" class="sq-btn sq-btn-outline h-12 px-10 text-base" data-filter-reset>{{ __st('souqify_pages.listing.reset') }}</a>
        </div>
    @else
        <noscript><button type="submit" class="sq-btn sq-btn-primary mt-2 w-full">{{ __st('souqify_pages.listing.apply') }}</button></noscript>
        @if ($query->activeFilters())
            <a href="{{ $resetUrl }}" class="mt-2 inline-flex w-fit items-center gap-1 text-sm text-sq-primary hover:underline" data-filter-reset><x-ui::icon name="x" :size="14" />{{ __st('souqify_pages.listing.clear_all') }}</a>
        @endif
    @endif
</form>
