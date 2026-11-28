{{--
    Ecommet mobile nav (Figma 1:2879, states board 1:4263): floating dark pill; the active
    item becomes a white pill with its label; the cart shows the live count.
--}}
@props(['items' => [], 'active' => null])
@php($active ??= request()->routeIs('storefront.home') ? 'home' : null)
<nav class="eco-mnav px-3 lg:hidden" aria-label="{{ __st('shell.mobile.label') }}" data-mobile-nav>
    <ul class="eco-mnav-bar" role="list">
        @foreach ($items as $key => $item)
            <li>
                <a href="{{ route($item['route']) }}" class="eco-mnav-item mo-press" @if ($active === $key) aria-current="page" @endif aria-label="{{ __st($item['label']) }}" @if ($key === 'cart') data-cart-target @endif>
                    <x-ui::icon :name="$item['icon']" :size="24" />
                    @if ($key === 'cart')<span class="eco-mnav-badge" data-cart-count>0</span>@endif
                    @if ($active === $key)<span class="text-base">{{ __st($item['label']) }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
{{-- Keeps the footer end clear of the floating bar, in the footer colour. --}}
<div class="h-[68px] bg-[#242424] lg:hidden" aria-hidden="true"></div>
