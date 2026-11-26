{{--
    Souqify promo-code box (Figma 1:75039 "Have a promo code?": grey "Enter code" field +
    dark "Apply"), or the applied code + Remove; the rejected reason underneath. Hooks for
    commerce/lines.js (data-coupon*). Not a <form>: it can sit inside the checkout form.
--}}
@props(['coupon' => null])
@php
    $applied = $coupon !== null && $coupon['applied'];
    $id = 'sq-coupon-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
@endphp
<div {{ $attributes->class(['grid gap-1']) }} data-coupon>
    @if ($applied)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#bbf7d0] bg-[#f0fdf4] px-3 py-2">
            <span class="flex min-w-0 items-center gap-2 text-sm font-medium text-[#166534]"><x-ui::icon name="tag" :size="16" class="shrink-0 text-sq-green" /><span class="truncate">{{ $coupon['code'] }}</span></span>
            <button type="button" class="shrink-0 text-xs text-sf-danger hover:underline" data-coupon-remove>{{ __st('checkout.coupon.remove') }}</button>
        </div>
    @else
        <div class="sq-coupon" role="group" aria-labelledby="{{ $id }}-label" data-coupon-form>
            <label id="{{ $id }}-label" for="{{ $id }}" class="sr-only">{{ __st('checkout.coupon.label') }}</label>
            <input id="{{ $id }}" name="code" type="text" autocomplete="off" maxlength="50" required value="{{ $coupon['code'] ?? '' }}" placeholder="{{ __st('cart.coupon_placeholder') }}" aria-describedby="{{ $id }}-error">
            <button type="button" class="disabled:opacity-60" data-coupon-apply>{{ __st('checkout.coupon.apply') }}</button>
        </div>
    @endif
    <p id="{{ $id }}-error" class="text-xs text-sf-danger" data-coupon-error @if ($coupon === null || $applied) hidden @endif>{{ $coupon !== null && ! $applied ? $coupon['message'] : '' }}</p>
</div>
