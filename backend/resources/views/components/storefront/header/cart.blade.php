{{--
    Cart entry (Ecommet 1:455): icon + count pill + "Cart"; only when the cart page exists, else a count-only button.
    The full (desktop) entry opens the mini cart drawer (1:1662, sf-checkout) instead of navigating; the drawer and the
    "Item added to cart" dialog are rendered once per page.
--}}
@props(['tone' => 'dark', 'count' => 0, 'compact' => false])
@php($href = Route::has('storefront.cart') ? route('storefront.cart') : null)
<{{ $href ? 'a' : 'span' }} @if ($href) href="{{ $href }}" @endif data-cart-target @if ($href && ! $compact) data-mini-cart-open @endif {{ $attributes->class(['relative inline-flex items-center gap-2', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark']) }} aria-label="{{ __st('shell.cart') }}">
    <x-ui::icon name="cart" :size="24" />
    @if ($compact)
        <span data-cart-count class="absolute -end-1.5 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-sf-ink px-1 text-[10px] text-white" @if (! $count) hidden @endif>{{ $count }}</span>
    @else
        <span class="grid justify-items-center text-sm leading-tight tracking-[0.5px]">
            <span data-cart-count class="grid h-4 min-w-[30px] place-items-center rounded-2xl bg-sf-ink px-1 text-sm text-white">{{ $count }}</span>
            {{ __st('shell.cart') }}
        </span>
    @endif
</{{ $href ? 'a' : 'span' }}>
@if ($href)
    @once
        {{-- The family's own drawer (components.storefront.{family}.inner.cart.drawer) when it has one. --}}
        @php($drawer = 'components.storefront.'.\App\Modules\Storefront\Support\StorefrontTheme::family().'.inner.cart.drawer')
        @if (view()->exists($drawer))
            @include($drawer)
        @else
            <x-sf::cart.drawer />
        @endif
    @endonce
@endif
