{{--
    Elora checkout summary (Figma 1:18245 right column / m 1:22186 + Storefront additions 98:100761):
    orange total, item rows (image, name, estimated delivery, price + struck price, − n + stepper /
    trash with the shared cart hooks), coupon, Subtotal / Shipping · carrier / Discount / Total due.
    Phones: the rows are cards and the totals sit in the "Order Summary" card (m 1:22186).
    Re-rendered by `storefront.checkout.fragment` when the address (pricing country), a quantity or
    the coupon changes — hooks: data-checkout-summary, data-checkout-total, data-cart-line, data-coupon*.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $shipping = $cart['shipping'];
    $coupon = $cart['coupon'];
    $delivery = $cart['delivery'];
    $carrier = $delivery['company_name'] ?? $shipping['rate'] ?? null;
    $eta = $delivery !== null && $shipping['available']
        ? __st('checkout.estimated_delivery', ['from' => \Illuminate\Support\Carbon::parse($delivery['min_date'])->translatedFormat(\Illuminate\Support\Carbon::parse($delivery['min_date'])->isSameMonth($delivery['max_date']) ? 'j' : 'j F'), 'to' => \Illuminate\Support\Carbon::parse($delivery['max_date'])->translatedFormat('j F')])
        : null;
@endphp
<div class="elc-sum" data-checkout-summary>
    <div class="elc-total">
        <p class="elc-total-label">{{ __st('checkout.total') }}</p>
        <p class="elc-total-value" data-checkout-total>{{ StorefrontCart::price($totals['grand_total']) }}</p>
    </div>

    <ul class="elc-lines" role="list">
        @foreach ($cart['lines'] as $line)
            @php
                $qty = (int) $line['quantity'];
                $removeLabel = __st('cart.remove_item', ['name' => $line['name']]);
                $discounted = StorefrontCart::positive($line['discount']);
            @endphp
            <li class="elc-line" data-swipe-remove data-remove-label="{{ $line['name'] }}" data-cart-line="{{ $line['id'] }}" data-quantity="{{ $qty }}">
                <div class="elc-line-in" data-swipe-content>
                    <a href="{{ route('storefront.product', ['product' => $line['store_product_id']]) }}" class="elc-img" tabindex="-1">
                        @if ($line['image_url'])
                            <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain" loading="lazy" decoding="async">
                        @else
                            <x-ui::icon name="image" :size="28" class="text-elo-line" />
                        @endif
                    </a>
                    <div class="elc-info">
                        <a href="{{ route('storefront.product', ['product' => $line['store_product_id']]) }}" class="elc-name" dir="auto" title="{{ $line['name'] }}">{{ $line['name'] }}</a>
                        @if ($line['variant'])
                            <p class="elc-variant" dir="auto">{{ $line['variant'] }}</p>
                        @endif
                        @if ($eta)
                            <p class="elc-eta">{{ $eta }}</p>
                        @endif
                        @if ($line['issue'] !== null)
                            <p class="elc-issue" role="status">{{ $line['message'] }}</p>
                        @endif
                        <div class="elc-line-bottom">
                            <p class="elc-prices">
                                <span class="elc-price">{{ StorefrontCart::price($line['total']) }}</span>
                                @if ($discounted)
                                    <del class="elc-was">{{ StorefrontCart::price($line['subtotal']) }}</del>
                                @endif
                            </p>
                            <div class="elc-stepper" data-elo-stepper>
                                <button type="button" data-swipe-remove-button class="@if ($qty > 1) hidden @endif" aria-label="{{ $removeLabel }}"><x-ui::icon name="elo-trash" :size="20" /></button>
                                <button type="button" data-elo-step="-1" class="@if ($qty <= 1) hidden @endif" aria-label="{{ __st('elora_pages.cart.decrease') }}"><x-ui::icon name="elo-minus" :size="18" /></button>
                                <label class="sr-only" for="elc-qty-{{ $line['id'] }}">{{ __st('cart.quantity_for', ['name' => $line['name']]) }}</label>
                                <input id="elc-qty-{{ $line['id'] }}" type="number" inputmode="numeric" min="1" max="99" value="{{ $qty }}" readonly data-cart-qty>
                                <button type="button" data-elo-step="1" aria-label="{{ __st('elora_pages.cart.increase') }}" @disabled($qty >= 99)><x-ui::icon name="elo-plus" :size="18" /></button>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="elc-card">
        <h2 class="elc-card-title">{{ __st('elora_pages.checkout.order_summary') }}</h2>
        <dl class="elc-rows">
            <div class="elc-row elc-row-main"><dt>{{ rtrim(__st('elora_pages.cart.subtotal'), ':') }}</dt><dd>{{ StorefrontCart::price($totals['items_total']) }}</dd></div>
            <div class="elc-row elc-row-ship">
                <dt>
                    <span>{{ __st('checkout.shipping_label') }}</span>
                    @if ($carrier)<small>{{ $carrier }}</small>@endif
                </dt>
                <dd>
                    @if (! $shipping['available'])
                        <span class="text-sf-danger">—</span>
                    @elseif ($shipping['free'])
                        {{ __st('checkout.free') }}
                    @else
                        {{ StorefrontCart::price($totals['shipping']) }}
                    @endif
                </dd>
            </div>
            @if ($coupon && $coupon['applied'] && StorefrontCart::positive($totals['coupon_discount']))
                <div class="elc-row elc-row-ship"><dt><span>{{ __st('checkout.discount_code', ['code' => $coupon['code']]) }}</span></dt><dd class="!text-elo-green">− {{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
            @endif
        </dl>
        <x-sf::elora.inner.coupon :coupon="$coupon" class="elc-coupon" />
        <div class="elc-due">
            <p>{{ __st('elora_pages.checkout.total_due') }}</p>
            <p>{{ StorefrontCart::price($totals['grand_total']) }}</p>
        </div>
        <p class="elc-note">{{ __st('elora_pages.checkout.amount_note') }}</p>
    </div>
</div>
