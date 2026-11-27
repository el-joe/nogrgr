{{--
    Phone category tabs under the header (m 1:2697): Recommended (current, black with a short
    underline) + store categories in grey, 12px medium, swipeable; 16px lead-in, runs to the edge.
--}}
@props(['tabs' => []])
@if (count($tabs) > 1)
    <nav {{ $attributes->class(['eco-strip bg-white pb-1']) }} aria-label="{{ __st('shell.nav.categories') }}">
        <div class="swiper" data-rail='{"spaceBetween":13,"slidesOffsetBefore":16,"slidesOffsetAfter":16}'>
            <ul class="swiper-wrapper" role="list">
                @foreach ($tabs as $index => $tab)
                    <li class="swiper-slide !w-auto">
                        <a href="{{ $index === 0 ? route('storefront.home') : route('storefront.category', ['slug' => $tab['key']]) }}" class="eco-tab eco-tab-dark eco-tab-short text-xs font-medium tracking-[0.5px]" @if ($index === 0) aria-current="page" @endif>{{ $tab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
@endif
