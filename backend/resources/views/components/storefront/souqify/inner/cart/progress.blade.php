{{--
    Souqify free-shipping meter (Figma 1:75039 top bar): orange track with "0 g" at the start,
    the remaining weight + "Free Shipping!" in the middle, a truck riding the end of the fill
    and the threshold at the end. Reached → green "free shipping unlocked" bar. No threshold →
    nothing.
--}}
@props(['free' => null])
@if ($free !== null)
    @php
        $grams = fn (int $value) => $value >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($value / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $value]);
        $remaining = (int) $free['remaining_grams'];
        $progress = (int) $free['progress'];
    @endphp
    <div {{ $attributes->class(['sq-shipbar', 'is-reached' => $free['reached']]) }} data-cart-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}" aria-label="{{ __st('cart.free_shipping_progress') }}">
        <span class="sq-shipbar-fill" data-width="{{ max($progress, 8) }}">
            <span class="sq-shipbar-truck" aria-hidden="true"><x-ui::icon name="truck" :size="26" /></span>
        </span>
        <span class="sq-shipbar-text">
            <span class="tabular-nums">{{ $grams((int) $free['weight_grams']) }}</span>
            <span class="min-w-0 truncate text-center">
                @if ($free['reached'])
                    {{ __st('cart.free_shipping_reached') }}
                @else
                    ( {{ $grams($remaining) }} ) {{ __st('souqify_pages.cart.free_label') }}
                @endif
            </span>
            <span class="tabular-nums">{{ $grams((int) $free['threshold_grams']) }}</span>
        </span>
    </div>
@endif
