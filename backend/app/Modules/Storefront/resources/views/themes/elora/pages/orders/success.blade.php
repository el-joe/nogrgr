{{--
    Elora order success (Figma m 1:23007, desktop = the same card centred): animated
    badge, "Congratulations", payment / order confirmation, black "Tracking order" and
    orange "Continue shopping", then the order recap (number, delivery estimate, ship-to,
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
        <section class="grid justify-items-center gap-5 rounded-2xl bg-white px-5 py-10 text-center shadow-[0_8px_28px_rgb(0_0_0/0.08)] sm:px-10" aria-labelledby="elo-success-title" data-order-success>
            <span @class(['elo-status-art', '!bg-sf-warning-bg !text-sf-warning' => $pending]) aria-hidden="true">
                <x-ui::icon :name="$pending ? 'clock' : 'check-circle'" :size="56" />
            </span>
            @if ($pending)
                <x-sf::order.badge tone="warning">{{ __st('orders.awaiting_payment') }}</x-sf::order.badge>
            @endif
            <div class="grid gap-2">
                <h1 id="elo-success-title" class="text-[28px] font-medium text-elo-ink">{{ $pending ? __st('orders.awaiting_payment') : __st('elora_pages.success.title') }}</h1>
                <p class="text-sm text-[#5e5e5e]">{{ $pending ? __st('orders.awaiting_text', ['number' => $d['number']]) : ($d['payment_status'] === 'paid' ? __st('elora_pages.success.paid') : __st('elora_pages.success.placed')) }}</p>
            </div>

            <div class="grid w-full gap-3">
                @if ($pay)
                    <a href="{{ $pay }}" class="elo-btn elo-btn-ink mo-press h-12 w-full text-base"><x-ui::icon name="credit-card" :size="18" />{{ __st('orders.complete_payment') }}</a>
                @else
                    <a href="{{ route('storefront.orders.tracking', $d['uuid']) }}" class="elo-btn elo-btn-ink mo-press h-12 w-full text-base">{{ __st('elora_pages.success.tracking') }}</a>
                @endif
                <a href="{{ $shop }}" class="elo-btn elo-btn-primary mo-press h-12 w-full text-base">{{ __st('orders.continue_shopping') }}</a>
            </div>

            <dl class="grid w-full gap-2 rounded-lg bg-elo-soft px-4 py-3.5 text-sm">
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('elora_pages.success.order') }}</dt><dd class="font-medium text-elo-ink" dir="ltr">{{ $d['number'] }}</dd></div>
                @if ($min)
                    <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.estimated') }}</dt><dd class="font-medium text-elo-ink">{{ $min->translatedFormat('D j') }}@if ($max && ! $max->isSameDay($min)) – {{ $max->translatedFormat('D j M') }}@else {{ $min->translatedFormat('M') }}@endif</dd></div>
                @endif
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.ship_to') }}</dt><dd class="truncate font-medium text-elo-ink">{{ $shipTo ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.payment') }}</dt><dd class="font-medium text-elo-ink">{{ collect([$d['payment_label'], $money($d['totals']['grand_total'])])->filter()->implode(' · ') }}</dd></div>
            </dl>

            <ul class="grid w-full gap-3" data-reveal-stagger>
                @foreach ($d['items'] as $item)
                    <li class="flex items-center gap-3 text-start" data-reveal="fade-up">
                        <x-sf::order.thumb :src="$item['image_url']" :size="48" />
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="truncate text-sm text-elo-ink">{{ $item['name'] }}</span>
                            <span class="text-xs text-sf-muted">{{ collect([$item['variant'], '×'.$item['quantity']])->filter()->implode(' · ') }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-medium text-elo-ink">{{ $money($item['total']) }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('storefront.orders.show', $d['uuid']) }}" class="text-xs font-medium text-elo-primary hover:underline">{{ __st('orders.view_details') }}</a>
        </section>
    </div>
</x-sf::layout>
