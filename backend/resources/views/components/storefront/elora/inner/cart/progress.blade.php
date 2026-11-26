{{--
    Elora free-shipping meter (Figma 1:17541 top box · m 1:22029): "Free Shipping!" + how
    much weight is left, orange bar with a truck riding its end. No threshold → nothing.
--}}
@props(['free' => null, 'compact' => false])
@if ($free !== null)
    @php
        $remaining = (int) $free['remaining_grams'];
        $weight = $remaining >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($remaining / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $remaining]);
    @endphp
    <div {{ $attributes->class(['elo-freeship']) }} data-cart-progress>
        <div class="flex items-center gap-3">
            <p @class(['min-w-0 flex-1 text-elo-ink', 'text-xs' => $compact, 'text-sm' => ! $compact])>
                @if ($free['reached'])
                    <span class="font-medium text-elo-green">{{ __st('cart.free_shipping_reached') }}</span>
                @else
                    <strong class="font-medium text-elo-primary">{{ __st('elora_pages.cart.free_label') }}</strong>
                    {!! __st('cart.free_shipping_more', ['weight' => '<strong class="font-semibold">'.e($weight).'</strong>', 'free' => e(__st('cart.free_shipping'))]) !!}
                @endif
            </p>
            <x-ui::icon name="truck" :size="22" class="shrink-0 text-elo-primary" />
        </div>
        <div class="elo-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $free['progress'] }}" aria-label="{{ __st('cart.free_shipping_progress') }}">
            <span data-width="{{ $free['progress'] }}"></span>
        </div>
    </div>
@endif
