{{--
    Elora filter form (Figma 1:17172 sidebar · m 1:22799 sheet): Category (desktop), then
    the groups in the design's order (Support/Listing/FilterGroups). Long lists show the
    eight most used values with a search box and "Show more"; nothing scrolls inside a
    group. A GET form on the listing URL (works without JS): the sidebar applies each change
    at once (data-elo-autosubmit), the sheet keeps "show ( n result )" + "reset".
    Inputs: $query, $result, $mode (sidebar|sheet), $category, $title, $kind, $parentCategory.
--}}
@use('App\Modules\Storefront\Enums\ListingKind')
@use('App\Modules\Storefront\Support\Listing\FilterGroups')
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingQuery $query */
    $facets = $result->facets;
    $sheet = ($mode ?? 'sidebar') === 'sheet';
    $id = $sheet ? 'elp-fs' : 'elp-fb';
    $category ??= null;
    $parentCategory ??= null;
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $rate = (float) ($context->currency()->rate ?? 1);
    $asInput = static fn (?int $minor): ?string => $minor === null ? null : (string) round($minor / 100 * ($rate > 0 ? $rate : 1), 2);
    $priceMin = $query->minInput ?? $asInput($query->priceMin);
    $priceMax = $query->maxInput ?? $asInput($query->priceMax);
    $keep = array_filter([
        'q' => $query->term,
        'sort' => $query->sort !== $kind->defaultSort() ? $query->sort->value : null,
        'period' => $kind === ListingKind::BestSelling && $query->period?->value !== 30 ? $query->period?->value : null,
    ]);
    $resetUrl = url()->current().($keep ? '?'.http_build_query($keep) : '');
    /* "New in" (quick pill) has no sidebar control outside the New In page: carry it along. */
    $hidden = $keep + array_filter(['added' => $kind !== ListingKind::NewIn ? $query->addedWithinDays : null, 'discount' => $kind !== ListingKind::Offers ? $query->minDiscount : null]);
    $groups = FilterGroups::order($facets['options']);
    $firstOptions = collect($groups)->firstWhere('type', 'options')['attribute']['id'] ?? null;
@endphp
<form method="GET" action="{{ url()->current() }}" class="elp-form" data-filter-form @if ($sheet) data-elp-sheet-form @else data-elo-autosubmit @endif>
    @foreach ($hidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    @if ($sheet)
        {{-- The sheet draws no category block (m 1:22799): keep a desktop choice when applying. --}}
        @foreach ($query->categoryIds as $categoryId)
            <input type="hidden" name="cat[]" value="{{ $categoryId }}">
        @endforeach
    @elseif ($category !== null || $facets['categories'] !== [])
        <details class="elp-group elp-group-category" data-elp-group="category" open>
            <summary><span>{{ __st('catalog.filters.category') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
            <div class="elp-group-body">
                @if ($category !== null)
                    {{-- The open category, ticked as drawn; unticking goes one level up. --}}
                    <a href="{{ $upUrl }}" class="elp-check elp-check-current" aria-current="page"><span class="elp-box" aria-hidden="true"></span><span class="elp-check-name">{{ $title }}</span></a>
                @endif
                @foreach ($facets['categories'] as $index => $option)
                    <label class="elp-check" @if ($index >= FilterGroups::VISIBLE && ! in_array($option['id'], $query->categoryIds, true)) data-more @endif><input type="checkbox" name="cat[]" value="{{ $option['id'] }}" @checked(in_array($option['id'], $query->categoryIds, true))><span class="elp-check-name" title="{{ $option['name'] }}">{{ $option['name'] }}</span><span class="elp-count">{{ $option['count'] }}</span></label>
                @endforeach
                @if (count($facets['categories']) > FilterGroups::VISIBLE)
                    <div class="elp-toggles"><button type="button" class="elp-toggle" data-elp-more>{{ __st('elora_pages.listing.show_more') }}</button><button type="button" class="elp-toggle" data-elp-less hidden>{{ __st('elora_pages.listing.show_less') }}</button></div>
                @endif
            </div>
        </details>
    @endif

    <div class="elp-groups">
        @foreach ($groups as $group)
            @switch($group['type'])
                @case('options')
                    @php
                        $attribute = $group['attribute'];
                        $selected = $query->options[$attribute['id']] ?? [];
                        $rows = FilterGroups::rows($attribute['values'], $selected);
                        $long = count($rows) > FilterGroups::VISIBLE;
                        $name = $attribute['name'];
                    @endphp
                    <details class="elp-group" data-elp-group="{{ $group['key'] }}" data-elp-facet @if ($selected !== [] || $attribute['id'] === $firstOptions) open @endif>
                        <summary><span>{{ $name }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                        <div class="elp-group-body">
                            @if ($long)
                                <label class="sr-only" for="{{ $id }}-s-{{ $attribute['id'] }}">{{ __st('elora_pages.listing.facet_search', ['name' => $name]) }}</label>
                                <input id="{{ $id }}-s-{{ $attribute['id'] }}" type="search" class="elp-field" placeholder="{{ __st('elora_pages.listing.facet_search', ['name' => $name]) }}" data-elp-search autocomplete="off">
                                <p class="elp-popular" data-elp-popular>{{ __st($group['key'] === 'brand' ? 'elora_pages.listing.popular_brands' : 'elora_pages.listing.popular') }}</p>
                            @endif
                            @foreach ($rows as $row)
                                @php($dot = FilterGroups::dot($group['key'], $row['value']))
                                <label class="elp-check" data-elp-value="{{ mb_strtolower($row['value']) }}" @if ($row['more']) data-more @endif>
                                    <input type="checkbox" name="opt[{{ $attribute['id'] }}][]" value="{{ $row['value'] }}" @checked($row['checked'])>
                                    @if ($dot)<span class="elp-dot" data-swatch="{{ $dot }}" aria-hidden="true"></span>@endif
                                    <span class="elp-check-name" title="{{ $row['value'] }}" dir="auto">{{ $row['value'] }}</span>
                                    <span class="elp-count">{{ $row['count'] }}</span>
                                </label>
                            @endforeach
                            @if ($long)
                                <p class="elp-none" data-elp-none hidden>{{ __st('elora_pages.listing.facet_none') }}</p>
                                <div class="elp-toggles"><button type="button" class="elp-toggle" data-elp-more>{{ __st('elora_pages.listing.show_more') }}</button><button type="button" class="elp-toggle" data-elp-less hidden>{{ __st('elora_pages.listing.show_less') }}</button></div>
                            @endif
                        </div>
                    </details>
                    @break

                @case('promo')
                    <details class="elp-group" data-elp-group="promo" @if ($query->onSale || $query->flashOnly || $query->featuredOnly || ($kind === ListingKind::Offers && $query->minDiscount)) open @endif>
                        <summary><span>{{ __st('elora_pages.listing.promo') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                        <div class="elp-group-body">
                            @foreach ([['sale', 'on_sale', $query->onSale], ['flash', 'flash_sale', $query->flashOnly], ['featured', 'featured', $query->featuredOnly]] as [$param, $text, $checked])
                                <label class="elp-check"><input type="checkbox" name="{{ $param }}" value="1" @checked($checked)><span class="elp-check-name">{{ __st('elora_pages.listing.'.$text) }}</span><span class="elp-count">{{ $facets['promo'][$param] }}</span></label>
                            @endforeach
                            @if ($kind === ListingKind::Offers)
                                @foreach ([10, 20, 30, 50] as $value)
                                    <label class="elp-check"><input type="radio" name="discount" value="{{ $value }}" @checked($query->minDiscount === $value)><span class="elp-check-name">{{ __st('catalog.filters.discount_min', ['percent' => $value]) }}</span></label>
                                @endforeach
                            @endif
                        </div>
                    </details>
                    @break

                @case('price')
                    <details class="elp-group" data-elp-group="price" @if ($priceMin !== null || $priceMax !== null) open @endif>
                        <summary><span>{{ __st('elora_pages.listing.price') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                        <div class="elp-group-body">
                            <div class="elp-price">
                                <label class="sr-only" for="{{ $id }}-min">{{ __st('elora_pages.listing.price_min', ['currency' => $context->currencyCode()]) }}</label>
                                <input id="{{ $id }}-min" type="number" name="min" min="0" step="0.01" inputmode="decimal" class="elp-field" placeholder="{{ __st('elora_pages.listing.min') }}" value="{{ $priceMin }}">
                                <label class="sr-only" for="{{ $id }}-max">{{ __st('elora_pages.listing.price_max', ['currency' => $context->currencyCode()]) }}</label>
                                <input id="{{ $id }}-max" type="number" name="max" min="0" step="0.01" inputmode="decimal" class="elp-field" placeholder="{{ __st('elora_pages.listing.max') }}" value="{{ $priceMax }}">
                            </div>
                        </div>
                    </details>
                    @break

                @case('rating')
                    <details class="elp-group" data-elp-group="rating" @if ($query->minRating) open @endif>
                        <summary><span>{{ __st('elora_pages.listing.rating') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                        <div class="elp-group-body">
                            @if ($query->minRating)
                                <label class="elp-check"><input type="radio" name="rating" value=""><span class="elp-check-name">{{ __st('catalog.filters.any') }}</span></label>
                            @endif
                            @if ($kind === ListingKind::TopRated || $query->minRating === 5)
                                <label class="elp-check"><input type="radio" name="rating" value="5" @checked($query->minRating === 5)><x-sf::stars :rating="5" :size="12" /><span class="elp-check-name">{{ __st('catalog.filters.five_only') }}</span></label>
                            @endif
                            @foreach ($facets['stars'] as $stars => $count)
                                <label class="elp-check"><input type="radio" name="rating" value="{{ $stars }}" @checked($query->minRating === $stars)><x-sf::stars :rating="$stars" :size="12" /><span class="elp-check-name">{{ __st('catalog.filters.and_up') }}</span><span class="elp-count">{{ $count }}</span></label>
                            @endforeach
                        </div>
                    </details>
                    @break

                @case('availability')
                    @if ($kind === ListingKind::NewIn)
                        <details class="elp-group" data-elp-group="added" @if ($query->addedWithinDays) open @endif>
                            <summary><span>{{ __st('catalog.filters.added') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                            <div class="elp-group-body">
                                @foreach (['' => 'catalog.filters.any', '7' => 'catalog.filters.added_7', '30' => 'catalog.filters.added_30'] as $value => $label)
                                    <label class="elp-check"><input type="radio" name="added" value="{{ $value }}" @checked((string) $query->addedWithinDays === (string) $value)><span class="elp-check-name">{{ __st($label) }}</span></label>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <details class="elp-group" data-elp-group="availability" @if ($query->inStockOnly || $query->outOfStockOnly) open @endif>
                        <summary><span>{{ __st('catalog.filters.availability') }}</span><span class="elp-ico elp-ico-chevron" aria-hidden="true"></span></summary>
                        <div class="elp-group-body">
                            <label class="elp-check"><input type="checkbox" name="in_stock" value="1" data-elp-exclusive="availability" @checked($query->inStockOnly)><span class="elp-check-name">{{ __st('catalog.filters.in_stock') }}</span><span class="elp-count">{{ $facets['in_stock'] }}</span></label>
                            <label class="elp-check"><input type="checkbox" name="out_of_stock" value="1" data-elp-exclusive="availability" @checked($query->outOfStockOnly)><span class="elp-check-name">{{ __st('catalog.card.out_of_stock') }}</span><span class="elp-count">{{ $facets['out_of_stock'] }}</span></label>
                        </div>
                    </details>
                    @break
            @endswitch
        @endforeach
    </div>

    @if ($sheet)
        <div class="elp-sheet-foot">
            <button type="submit" class="elp-show"><strong>{{ __st('elora_pages.listing.show') }}</strong> <span data-elp-count data-template="{{ __st('elora_pages.listing.result_count', ['count' => '__n__']) }}" aria-live="polite">{{ __st('elora_pages.listing.result_count', ['count' => $result->total]) }}</span></button>
            <a href="{{ $resetUrl }}" class="elp-reset" data-filter-reset>{{ __st('elora_pages.listing.reset') }}</a>
        </div>
    @else
        <noscript><button type="submit" class="elo-btn elo-btn-primary mt-2 w-full">{{ __st('elora_pages.listing.apply') }}</button></noscript>
        @if ($query->activeFilters())
            <a href="{{ $resetUrl }}" class="elp-clear" data-filter-reset data-elp-nav><x-ui::icon name="x" :size="14" />{{ __st('elora_pages.listing.clear_all') }}</a>
        @endif
    @endif
</form>
