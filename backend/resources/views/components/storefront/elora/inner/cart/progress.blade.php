{{--
    Elora free-shipping meter (Figma 1:17541 top box · m 1:22029): "Free Shipping!" + how much weight
    is left, orange bar with a truck riding its end (above the bar on desktop, on the bar on phones).
    `cart` = the cart page version; the default / compact one is the mini cart hint.
    No weight threshold: the mini cart shows nothing; the cart page keeps its box (given `shipping`)
    and states what shipping really costs there (free / fee / not available), never a fake promise.
--}}
@props(['free' => null, 'compact' => false, 'cart' => false, 'shipping' => null])
@if ($free !== null)
    @php
        $remaining = (int) $free['remaining_grams'];
        $weight = $remaining >= 1000 ? __st('cart.weight_kg', ['value' => rtrim(rtrim(number_format($remaining / 1000, 2), '0'), '.')]) : __st('cart.weight_g', ['value' => $remaining]);
        $label = __st('cart.free_shipping_progress');
    @endphp
    @if ($cart)
        <div {{ $attributes->class(['elo-freeship elo-freeship-cart']) }} data-cart-progress>
            <p class="elo-freeship-text">
                @if ($free['reached'])
                    <strong class="elo-freeship-lead elo-freeship-reached">{{ __st('cart.free_shipping_reached') }}</strong>
                @else
                    <strong class="elo-freeship-lead max-lg:hidden">{{ __st('elora_pages.cart.free_label') }}</strong>
                    <span>{!! __st('elora_pages.cart.away_from', ['weight' => '<b>'.e($weight).'</b>']) !!}</span>
                    <span class="max-lg:hidden">{{ __st('cart.free_shipping') }}.</span>
                    <strong class="elo-freeship-end lg:hidden">{{ __st('elora_pages.cart.free_label') }}</strong>
                @endif
            </p>
            <div class="elo-track" data-width="{{ $free['progress'] }}">
                <div class="elo-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $free['progress'] }}" aria-label="{{ $label }}"><span></span></div>
                <x-ui::icon name="elo-truck-speed" :size="33" class="elo-truck elo-truck-desk max-lg:hidden" />
                <x-ui::icon name="elo-truck-bar" :size="27" class="elo-truck elo-truck-bar lg:hidden" />
            </div>
        </div>
    @else
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
            <div class="elo-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $free['progress'] }}" aria-label="{{ $label }}">
                <span data-width="{{ $free['progress'] }}"></span>
            </div>
        </div>
    @endif
@elseif ($cart && $shipping !== null)
    @php($shipsFree = $shipping['available'] && $shipping['free'])
    <div {{ $attributes->class(['elo-freeship elo-freeship-cart']) }} data-cart-progress data-no-threshold>
        <p class="elo-freeship-text">
            @if ($shipsFree)
                <strong class="elo-freeship-lead elo-freeship-reached">{{ __st('cart.free_shipping_reached') }}</strong>
            @elseif ($shipping['available'])
                <strong class="elo-freeship-lead">{{ __st('elora_pages.cart.shipping_label') }}</strong>
                <span>{!! __st('elora_pages.cart.shipping_for_cart', ['amount' => '<b>'.e(\App\Modules\Storefront\Services\Checkout\StorefrontCart::price($shipping['amount'])).'</b>']) !!}</span>
            @else
                <strong class="elo-freeship-lead">{{ __st('elora_pages.cart.shipping_label') }}</strong>
                <span>{{ $shipping['message'] ?: __st('elora_pages.cart.shipping_later') }}</span>
            @endif
        </p>
        <div class="elo-track" data-width="{{ $shipsFree ? 100 : 0 }}" aria-hidden="true">
            <div class="elo-meter"><span></span></div>
            <x-ui::icon name="elo-truck-speed" :size="33" class="elo-truck elo-truck-desk max-lg:hidden" />
            <x-ui::icon name="elo-truck-bar" :size="27" class="elo-truck elo-truck-bar lg:hidden" />
        </div>
    </div>
@endif
