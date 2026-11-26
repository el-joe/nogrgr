{{--
    Elora checkout summary (Figma 1:18245 right column / m 1:22186 + Storefront additions
    98:100761 in Elora mode): big orange total, item rows (image, name, variant · qty, line
    total, issue), coupon, Subtotal / Shipping · carrier / Discount / Total due and the
    delivery estimate. Re-rendered by `storefront.checkout.fragment` when the address
    (pricing country) or the coupon changes — same hooks as x-sf::checkout.summary.
--}}
@props(['cart'])
@use('App\Modules\Storefront\Services\Checkout\StorefrontCart')
@php
    $totals = $cart['totals'];
    $shipping = $cart['shipping'];
    $coupon = $cart['coupon'];
    $delivery = $cart['delivery'];
    $carrier = $delivery['company_name'] ?? $shipping['rate'] ?? null;
@endphp
<div class="elo-summary gap-5 p-5" data-checkout-summary>
    <div class="grid gap-0.5">
        <h2 class="text-xs text-sf-muted">{{ __st('checkout.summary') }} · {{ __st('checkout.total') }}</h2>
        <p class="text-2xl font-medium text-elo-primary" data-checkout-total>{{ StorefrontCart::price($totals['grand_total']) }}</p>
    </div>
    <ul class="grid gap-4" role="list">
        @foreach ($cart['lines'] as $line)
            <li class="flex items-center gap-3">
                <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-md bg-elo-soft lg:size-[72px]">
                    @if ($line['image_url'])
                        <img src="{{ $line['image_url'] }}" alt="" class="size-full object-contain bg-media" loading="lazy" decoding="async">
                    @else
                        <x-ui::icon name="image" :size="22" class="text-elo-line" />
                    @endif
                </span>
                <span class="grid min-w-0 flex-1 gap-1">
                    <span class="line-clamp-2 text-xs text-elo-ink">{{ $line['name'] }}</span>
                    <span class="truncate text-xs text-sf-muted">{{ collect([$line['variant'], __st('cart.qty', ['qty' => $line['quantity']])])->filter()->implode(' · ') }}</span>
                    @if ($line['issue'] !== null)
                        <span class="text-xs text-sf-danger">{{ $line['message'] }}</span>
                    @endif
                </span>
                <span class="shrink-0 text-sm text-elo-ink">{{ StorefrontCart::price($line['total']) }}</span>
            </li>
        @endforeach
    </ul>

    <x-sf::elora.inner.coupon :coupon="$coupon" />

    <dl class="grid gap-3 border-t border-elo-line pt-4 text-sm">
        <div class="flex items-start gap-3"><dt class="flex-1 text-elo-ink">{{ __st('checkout.items_subtotal') }}</dt><dd class="text-elo-ink">{{ StorefrontCart::price($totals['items_total']) }}</dd></div>
        <div class="flex items-start gap-3">
            <dt class="flex-1 text-sf-muted">{{ $carrier ? __st('checkout.shipping_via', ['carrier' => $carrier]) : __st('checkout.shipping_label') }}</dt>
            <dd class="text-sf-muted">
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
            <div class="flex items-start gap-3"><dt class="flex-1 text-sf-muted">{{ __st('checkout.discount_code', ['code' => $coupon['code']]) }}</dt><dd class="text-elo-green">− {{ StorefrontCart::price($totals['coupon_discount']) }}</dd></div>
        @endif
        <div class="flex items-center gap-3 border-t border-elo-line pt-4 text-elo-ink">
            <dt class="flex-1 text-base font-medium">{{ __st('elora_pages.checkout.total_due') }}</dt>
            <dd class="text-xl font-medium">{{ StorefrontCart::price($totals['grand_total']) }}</dd>
        </div>
    </dl>

    @if ($delivery !== null && $shipping['available'])
        <p class="flex items-center gap-2 text-xs text-elo-primary">
            <x-ui::icon name="truck" :size="16" class="shrink-0" />
            {{ __st('checkout.estimated_delivery', ['from' => \Illuminate\Support\Carbon::parse($delivery['min_date'])->translatedFormat('j M'), 'to' => \Illuminate\Support\Carbon::parse($delivery['max_date'])->translatedFormat('j M')]) }}
        </p>
    @endif
</div>
