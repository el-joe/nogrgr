{{--
    Souqify phone tab bar (Figma 1:80723 / m 1:78202 bottom): white bar, icon + label, the
    active tab in the variant accent with a tinted rounded icon chip; cart count on the cart tab.
--}}
@props(['items' => [], 'active' => null])
<nav class="sq-tabbar fixed inset-x-0 bottom-0 z-40 border-t border-sf-border bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="{{ __st('shell.mobile.label') }}">
    <ul @class(['grid h-[68px]', [2 => 'grid-cols-2', 3 => 'grid-cols-3', 4 => 'grid-cols-4', 5 => 'grid-cols-5'][count($items)] ?? 'grid-cols-5']) role="list">
        @foreach ($items as $key => $item)
            <li class="flex">
                <a href="{{ route($item['route']) }}" @class(['sq-tab mo-press', 'is-active' => $active === $key]) @if ($active === $key) aria-current="page" @endif>
                    <span class="sq-tab-icon relative"><x-ui::icon :name="$item['icon']" :size="22" />@if ($key === 'cart')<span data-cart-count class="absolute -end-2 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-sf-accent px-1 text-[10px] text-white" hidden>0</span>@endif</span>
                    {{ __st($item['label']) }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<div class="h-[68px] lg:hidden" aria-hidden="true"></div>
