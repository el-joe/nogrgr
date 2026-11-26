{{--
    Coupon box shared by the cart summary (variant "cart", Figma 1:1500: 48 px field + grey
    "Apply") and the checkout summary (variant "checkout", old Ecommet checkout markup,
    spec 08). Applied → green box with the code + Remove; a rejected code shows its reason.
    The commerce script posts to /store-api/v1/cart/coupon and re-renders the page fragment. Not a <form>: the
    checkout summary sits inside the checkout form on phones.
--}}
@props(['coupon' => null, 'variant' => 'cart'])
@php
    $cart = $variant === 'cart';
    $applied = $coupon !== null && $coupon['applied'];
    $id = 'sf-coupon-'.$variant.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
@endphp
<div {{ $attributes->class(['grid gap-1']) }} data-coupon>
    @if ($applied)
        <div class="flex items-center justify-between gap-3 rounded-[6px] border border-[#bbf7d0] bg-[#f0fdf4] px-4 py-2.5">
            <span class="flex min-w-0 items-center gap-2 text-[13px] font-medium text-[#166534]"><x-ui::icon name="tag" :size="16" class="shrink-0 text-[#16a34a]" /><span class="truncate">{{ $coupon['code'] }}</span></span>
            <button type="button" class="shrink-0 text-xs text-[#dc2626] hover:underline" data-coupon-remove>{{ __st('checkout.coupon.remove') }}</button>
        </div>
    @else
        <div role="group" aria-labelledby="{{ $id }}-label" @class([
            'flex items-center overflow-hidden bg-white',
            'h-12 rounded-lg border border-[#cacaca]' => $cart,
            'h-10 rounded-[6px] border border-[#dcdcdc]' => ! $cart,
        ]) data-coupon-form>
            <label id="{{ $id }}-label" for="{{ $id }}" class="sr-only">{{ __st('checkout.coupon.label') }}</label>
            <input id="{{ $id }}" name="code" type="text" autocomplete="off" maxlength="50" required value="{{ $coupon['code'] ?? '' }}"
                placeholder="{{ $cart ? __st('cart.coupon_placeholder') : __st('checkout.coupon.placeholder') }}"
                @class(['h-full min-w-0 flex-1 bg-transparent px-3 outline-none', 'text-base text-eco-black placeholder:text-[#686868]' => $cart, 'text-[13px] text-[#333] placeholder:text-[#999]' => ! $cart])
                aria-describedby="{{ $id }}-error">
            <button type="button" @class([
                'h-full shrink-0 text-white transition-colors disabled:opacity-60',
                'w-32 bg-[#717171] text-xl font-medium hover:bg-[#555]' => $cart,
                'bg-[#787878] px-5 text-[13px] font-medium hover:bg-[#555]' => ! $cart,
            ]) data-coupon-apply>{{ __st('checkout.coupon.apply') }}</button>
        </div>
    @endif
    <p id="{{ $id }}-error" class="text-[11px] text-[#dc2626]" data-coupon-error @if ($coupon === null || $applied) hidden @endif>{{ $coupon !== null && ! $applied ? $coupon['message'] : '' }}</p>
</div>
