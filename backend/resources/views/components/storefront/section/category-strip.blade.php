{{-- Phone category tabs under the header (m 1:2656): Recommended (current) + store categories, swipeable. --}}
@props(['tabs' => []])
@if (count($tabs) > 1)
    <nav {{ $attributes->class(['border-b border-eco-stroke bg-white']) }} aria-label="{{ __st('shell.nav.categories') }}">
        <div class="swiper px-4" data-rail='{"spaceBetween":16}'>
            <ul class="swiper-wrapper" role="list">
                @foreach ($tabs as $index => $tab)
                    <li class="swiper-slide !w-auto">
                        <a href="{{ $index === 0 ? route('storefront.home') : route('storefront.category', ['slug' => $tab['key']]) }}" class="eco-tab eco-tab-dark text-xs" @if ($index === 0) aria-current="page" @endif>{{ $tab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
@endif
