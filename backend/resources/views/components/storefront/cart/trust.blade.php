{{--
    Trust blocks of the cart summary (1:1442): Safe Payment Options, payment methods,
    Purchase Protection, Secure privacy, Delivery guarantee. `compact` = the phone strip of
    four icon + label tiles (m 1:3210).
--}}
@props(['compact' => false])
@php
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $privacy = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => 'privacy']) : null;
    $returns = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Returns->value]) : null;
@endphp
@if ($compact)
    <ul class="grid grid-cols-4 gap-2 border-y-8 border-[#f6f6f6] px-1 py-3 text-center text-xs leading-tight text-eco-green" role="list">
        <li class="grid justify-items-center gap-1"><x-ui::icon name="shield" :size="20" />{{ __st('cart.trust.safe_payment') }}</li>
        <li class="grid justify-items-center gap-1"><x-ui::icon name="lock" :size="20" />{{ __st('cart.trust.secure_privacy') }}</li>
        <li class="grid justify-items-center gap-1"><x-ui::icon name="truck-tick" :size="20" />{{ __st('cart.trust.delivery') }}</li>
        <li class="grid justify-items-center gap-1"><x-ui::icon name="shield" :size="20" />{{ __st('cart.trust.protection', ['store' => $store]) }}</li>
    </ul>
@else
    <div {{ $attributes->class(['grid gap-8']) }}>
        <section class="grid gap-3">
            <h3 class="flex items-center gap-2 text-lg font-medium text-eco-black"><x-ui::icon name="security-time" :size="24" class="text-eco-green" />{{ __st('cart.trust.safe_payment') }}</h3>
            <p class="text-base text-eco-gray"><span class="text-eco-green">{{ __st('cart.trust.safe_payment_lead', ['store' => $store]) }}</span> {{ __st('cart.trust.safe_payment_body') }}</p>
        </section>
        <section class="grid gap-3">
            <h3 class="text-xl font-medium text-eco-black">{{ __st('cart.trust.methods') }}</h3>
            <x-sf::cart.pay-badges class="p-2" />
        </section>
        <section class="grid gap-3">
            <h3 class="flex items-center gap-2 text-lg font-medium text-eco-black"><x-ui::icon name="security-time" :size="24" class="text-eco-green" />{{ __st('cart.trust.protection', ['store' => $store]) }}</h3>
            <p class="text-sm tracking-[0.5px] text-eco-gray">{{ __st('cart.trust.protection_body') }}</p>
            @if ($returns)
                <a href="{{ $returns }}" class="inline-flex w-fit items-center gap-2 text-sm tracking-[0.5px] text-eco-gray hover:text-eco-black">{{ __st('cart.trust.program_terms') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
            @endif
        </section>
        <section class="grid gap-3">
            <h3 class="flex items-center gap-2 text-lg font-medium text-eco-black"><x-ui::icon name="lock" :size="21" class="text-eco-green" />{{ __st('cart.trust.secure_privacy') }}</h3>
            <p class="text-sm tracking-[0.5px] text-eco-gray">{{ __st('cart.trust.privacy_body') }}</p>
            @if ($privacy)
                <a href="{{ $privacy }}" class="inline-flex w-fit items-center gap-2 text-sm tracking-[0.5px] text-eco-gray hover:text-eco-black">{{ __st('cart.trust.learn_more') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
            @endif
        </section>
        <section class="grid gap-3">
            <h3 class="flex items-center gap-2 text-lg font-medium text-eco-black"><x-ui::icon name="truck-tick" :size="24" class="text-eco-green" />{{ __st('cart.trust.delivery') }}</h3>
            <ul class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm tracking-[0.5px] text-eco-gray" role="list">
                @foreach (['credit', 'damaged', 'no_update', 'no_delivery'] as $key)
                    <li class="flex items-center gap-2"><x-ui::icon name="check" :size="20" class="shrink-0 text-eco-green" />{{ __st('cart.trust.guarantee.'.$key) }}</li>
                @endforeach
            </ul>
        </section>
    </div>
@endif
