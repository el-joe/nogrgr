{{--
    Phone tab strip above a recommendations grid (m 1:3073 product, m 1:3210 cart): Recommended
    (the page's own list) + store categories (home.tab fragments). The parent section carries
    data-home-tabs and the grid data-tabs-grid (resources/js/storefront/catalog/listing.js).
--}}
@props(['tabs' => [], 'recommendedUrl', 'controls'])
@if (count($tabs) > 1)
    <div {{ $attributes->class(['swiper -mx-4 w-[calc(100%+32px)] lg:!hidden']) }} data-rail='{"spaceBetween":13,"slidesOffsetBefore":16,"slidesOffsetAfter":16}'>
        <div class="swiper-wrapper" role="tablist" aria-label="{{ __st('shell.nav.categories') }}">
            @foreach ($tabs as $index => $tab)
                <div class="swiper-slide !w-auto">
                    <button type="button" role="tab" class="eco-tab eco-tab-dark eco-tab-short text-xs font-medium tracking-[0.5px]" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="{{ $controls }}" data-tab-url="{{ $index === 0 ? $recommendedUrl : route('storefront.home.tab', ['tab' => $tab['key']]) }}">{{ $tab['label'] }}</button>
                </div>
            @endforeach
        </div>
    </div>
@endif
