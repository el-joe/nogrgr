{{--
    Elora phone navigation (m 1:21125 bottom): floating white pill — Home · Favorites ·
    Orders · Profile (active in the variant accent) — plus a round dark "My Cart" button with
    the count badge at the end.
--}}
@props(['items' => [], 'active' => null])
@php($cart = $items['cart'] ?? null)
@php($tabs = array_filter($items, fn ($key) => $key !== 'cart', ARRAY_FILTER_USE_KEY))
@php($centred = app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme()->variant > 1)
@if ($centred && $cart)
    {{-- v2–v6 (m 1:24137 …): white bar, raised accent "My Cart" circle in the middle. --}}
    @php($half = (int) ceil(count($tabs) / 2))
    <nav class="elo-mobile-bar lg:hidden" aria-label="{{ __st('shell.mobile.label') }}">
        <ul class="flex h-16 items-stretch" role="list">
            @foreach (array_slice($tabs, 0, $half, true) as $key => $item)
                <li class="flex-1"><a href="{{ route($item['route']) }}" @class(['mo-press flex h-full flex-col items-center justify-center gap-1 text-xs', 'text-elo-primary' => $active === $key, 'text-[#8f8f8f]' => $active !== $key]) @if ($active === $key) aria-current="page" @endif><x-ui::icon :name="$item['icon']" :size="24" />{{ __st($item['label']) }}</a></li>
            @endforeach
            <li class="flex-1">
                <a href="{{ route($cart['route']) }}" class="elo-mobile-cart elo-mobile-cart-raised mo-press" data-cart-target @if ($active === 'cart') aria-current="page" @endif>
                    <span class="relative"><x-ui::icon name="cart" :size="24" /><span data-cart-count class="absolute -end-3 -top-2 grid h-4 min-w-6 place-items-center rounded-full bg-white px-1 text-[10px] text-elo-primary">0</span></span>
                    <span class="text-[11px]">{{ __st('elora.mobile.my_cart') }}</span>
                </a>
            </li>
            @foreach (array_slice($tabs, $half, null, true) as $key => $item)
                <li class="flex-1"><a href="{{ route($item['route']) }}" @class(['mo-press flex h-full flex-col items-center justify-center gap-1 text-xs', 'text-elo-primary' => $active === $key, 'text-[#8f8f8f]' => $active !== $key]) @if ($active === $key) aria-current="page" @endif><x-ui::icon :name="$item['icon']" :size="24" />{{ __st($item['label']) }}</a></li>
            @endforeach
        </ul>
    </nav>
    <div class="h-20 lg:hidden" aria-hidden="true"></div>
@else
<nav class="elo-mobile-nav lg:hidden" aria-label="{{ __st('shell.mobile.label') }}">
    <ul class="elo-mobile-nav-pill" role="list">
        @foreach ($tabs as $key => $item)
            <li class="flex-1">
                <a href="{{ route($item['route']) }}" data-nav="{{ $key }}" @class(['mo-press flex h-full flex-col items-center justify-center gap-1 text-xs', 'text-elo-primary' => $active === $key, 'text-[#8f8f8f]' => $active !== $key]) @if ($active === $key) aria-current="page" @endif>
                    <x-ui::icon :name="$item['icon']" :size="24" />{{ __st($item['label']) }}
                </a>
            </li>
        @endforeach
    </ul>
    @if ($cart)
        <a href="{{ route($cart['route']) }}" class="elo-mobile-cart mo-press" data-cart-target @if ($active === 'cart') aria-current="page" @endif>
            <span class="relative"><x-ui::icon name="cart" :size="24" /><span data-cart-count class="absolute -end-3 -top-2 grid h-4 min-w-6 place-items-center rounded-full bg-elo-primary px-1 text-[10px] text-white">0</span></span>
            <span class="text-xs">{{ __st('elora.mobile.my_cart') }}</span>
        </a>
    @endif
</nav>
<div class="h-24 lg:hidden" aria-hidden="true"></div>
@endif
