{{--
    Souqify order success (Figma m 1:80522, desktop = the same card centred): animated
    payment illustration, "Congratulations", payment / order confirmation, dark "Tracking
    Order" and accent "Continue shopping", then the order recap (number, delivery estimate, ship-to,
    items). Pending payment → amber badge + "Complete payment".
    Inputs as the Ecommet view: $detail, $customer, $seo.
--}}
@php
    $money = fn ($amount) => \App\Modules\Storefront\Services\Account\AccountOrderPages::money($amount);
    $d = $detail;
    $pending = $d['status'] === 'pending_payment';
    $address = $d['shipping_address'] ?? [];
    $shipTo = collect([$address['address_line1'] ?? null, $address['city'] ?? null])->filter()->implode(', ');
    $min = $d['shipping']['delivery_min_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date']) : null;
    $max = $d['shipping']['delivery_max_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date']) : null;
    $pay = $pending && Route::has('storefront.checkout.pay') ? route('storefront.checkout.pay', ['order' => $d['uuid']]) : null;
    $shop = Route::has('storefront.home') ? route('storefront.home') : url('/');
@endphp
<x-sf::layout :title="__st('orders.details')" nav="account" page="order-success" :seo="$seo">
    <div class="mx-auto w-full max-w-[560px] px-4 py-8 lg:py-16">
        <section class="grid justify-items-center gap-5 rounded-3xl bg-white px-5 py-10 text-center shadow-[0_8px_28px_rgb(0_0_0/0.08)] sm:px-10" aria-labelledby="sq-success-title" data-order-success>
            @if ($pending)
                <span class="sq-status-art !bg-sf-warning-bg !text-sf-warning" aria-hidden="true"><x-ui::icon name="clock" :size="56" /></span>
            @else
                <span class="sq-success-art" aria-hidden="true"></span>
            @endif
            @if ($pending)
                <x-sf::order.badge tone="warning">{{ __st('orders.awaiting_payment') }}</x-sf::order.badge>
            @endif
            <div class="grid gap-2">
                <h1 id="sq-success-title" class="text-[28px] font-semibold text-black">{{ $pending ? __st('orders.awaiting_payment') : __st('souqify_pages.success.title') }}</h1>
                <p class="text-sm text-[#434655]">{{ $pending ? __st('orders.awaiting_text', ['number' => $d['number']]) : ($d['payment_status'] === 'paid' ? __st('souqify_pages.success.paid') : __st('souqify_pages.success.placed')) }}</p>
            </div>

            <div class="grid w-full gap-3">
                @if ($pay)
                    <a href="{{ $pay }}" class="sq-btn sq-btn-ink mo-press h-12 w-full rounded-full text-base"><x-ui::icon name="credit-card" :size="18" />{{ __st('orders.complete_payment') }}</a>
                @else
                    <a href="{{ route('storefront.orders.tracking', $d['uuid']) }}" class="sq-btn sq-btn-ink mo-press h-12 w-full rounded-full text-base">{{ __st('souqify_pages.success.tracking') }}</a>
                @endif
                <a href="{{ $shop }}" class="sq-btn sq-btn-primary mo-press h-12 w-full rounded-full text-base">{{ __st('orders.continue_shopping') }}</a>
            </div>

            <dl class="grid w-full gap-2 rounded-lg bg-sq-soft px-4 py-3.5 text-sm">
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('souqify_pages.success.order') }}</dt><dd class="font-medium text-sq-ink" dir="ltr">{{ $d['number'] }}</dd></div>
                @if ($min)
                    <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.estimated') }}</dt><dd class="font-medium text-sq-ink">{{ $min->translatedFormat('D j') }}@if ($max && ! $max->isSameDay($min)) – {{ $max->translatedFormat('D j M') }}@else {{ $min->translatedFormat('M') }}@endif</dd></div>
                @endif
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.ship_to') }}</dt><dd class="truncate font-medium text-sq-ink">{{ $shipTo ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.payment') }}</dt><dd class="font-medium text-sq-ink">{{ collect([$d['payment_label'], $money($d['totals']['grand_total'])])->filter()->implode(' · ') }}</dd></div>
            </dl>

            <ul class="grid w-full gap-3" data-reveal-stagger>
                @foreach ($d['items'] as $item)
                    <li class="flex items-center gap-3 text-start" data-reveal="fade-up">
                        <x-sf::order.thumb :src="$item['image_url']" :size="48" />
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="truncate text-sm text-sq-ink">{{ $item['name'] }}</span>
                            <span class="text-xs text-sf-muted">{{ collect([$item['variant'], '×'.$item['quantity']])->filter()->implode(' · ') }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-medium text-sq-ink">{{ $money($item['total']) }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('storefront.orders.show', $d['uuid']) }}" class="text-xs font-medium text-sq-primary hover:underline">{{ __st('orders.view_details') }}</a>
        </section>
    </div>
</x-sf::layout>
