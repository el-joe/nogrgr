{{-- Cart entry; only rendered when the cart route exists. --}}
@props(['tone' => 'dark', 'count' => 0, 'compact' => false])
@if (Route::has('storefront.cart'))
    <a href="{{ route('storefront.cart') }}" data-cart-target {{ $attributes->class(['relative inline-flex items-center gap-2', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark']) }} aria-label="{{ __st('shell.cart') }}">
        <x-ui::icon name="cart" :size="24" />
        @unless ($compact)
            <span class="grid justify-items-center text-sm leading-tight">
                <span data-cart-count class="grid h-4 min-w-[30px] place-items-center rounded-full bg-sf-ink px-1 text-xs text-white">{{ $count }}</span>
                {{ __st('shell.cart') }}
            </span>
        @endunless
    </a>
@endif
