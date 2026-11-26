{{--
    Free-shipping progress (Ecommet 1:1512): yellow bar + "Add 1.2 kg more to get free
    shipping" + percent, from the Store API cart `free_shipping` (grams). Reached → the
    "free shipping unlocked" line; no threshold for the country → nothing.
--}}
@props(['free' => null])
@if ($free !== null)
    @php
        $remaining = (int) $free['remaining_grams'];
        $weight = $remaining >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($remaining / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $remaining]);
    @endphp
    <div {{ $attributes->class(['flex items-start gap-3.5']) }} data-cart-progress>
        <div class="grid min-w-0 flex-1 gap-2.5 py-2">
            <div class="h-2 overflow-hidden rounded-3xl bg-eco-yellow/30 lg:h-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $free['progress'] }}" aria-label="{{ __st('cart.free_shipping_progress') }}">
                <div class="mo-progress-fill h-full w-[var(--sf-progress)] rounded-3xl bg-eco-yellow transition-[width] duration-500 ease-out" style="--sf-progress: {{ $free['progress'] }}%"></div>
            </div>
            <p class="text-sm text-eco-black lg:text-base">
                @if ($free['reached'])
                    <span class="text-eco-green">{{ __st('cart.free_shipping_reached') }}</span>
                @else
                    {!! __st('cart.free_shipping_more', [
                        'weight' => '<strong class="text-base font-semibold text-eco-yellow lg:text-lg">'.e($weight).'</strong>',
                        'free' => '<span class="text-eco-yellow">'.e(__st('cart.free_shipping')).'</span>',
                    ]) !!}
                @endif
            </p>
        </div>
        <p class="pt-0.5 text-base font-semibold tracking-[0.5px] text-eco-yellow lg:text-lg">{{ $free['progress'] }}%</p>
    </div>
@endif
