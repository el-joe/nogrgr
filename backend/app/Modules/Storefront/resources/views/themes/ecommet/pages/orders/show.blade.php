{{--
    Order details ⇄ tracking (SF-ECO-028/028a/028b/029, Figma Storefront additions
    100:102212 / m 100:102674; client states 1:2241 1:2126 1:2014). Items + totals, tracking
    timeline with the carrier link, addresses, actions (request return, review, reorder,
    invoice, cancel 2-step). $panel = details | tracking (tabs switch in place, URL follows).
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $d = $detail;
    $t = $d['totals'];
    $reviewItems = collect($d['items'])->where('reviewable', true)->map(fn ($item) => ['id' => $item['id'], 'name' => $item['name'], 'image' => $item['image_url'], 'variant' => $item['variant']])->values();
    $discount = (int) $t['flash_discount']['amount'];
    $meta = array_filter([
        __st('orders.placed_on', ['date' => $d['placed']?->translatedFormat('M j, Y')]),
        $d['delivered_at'] ? __st('orders.delivered_on', ['date' => $d['delivered_at']->translatedFormat('M j, Y')]) : null,
        $d['payment_label'] && $d['payment_status'] === 'paid' ? __st('orders.paid_with', ['method' => $d['payment_label']]) : $d['payment_status_label'],
    ]);
    $detailsUrl = route('storefront.orders.show', $d['uuid']);
    $trackingUrl = route('storefront.orders.tracking', $d['uuid']);
    $cancelled = $d['cancellation'] ?? null;
    $unit = fn (array $item) => $item['quantity'] > 1 ? __st('orders.qty_price', ['count' => $item['quantity'], 'price' => $money($item['unit_price'])]) : __st('orders.qty', ['count' => $item['quantity']]);
    $eta = $d['shipping']['delivery_min_date']
        ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date'])->translatedFormat('D j M').($d['shipping']['delivery_max_date'] && $d['shipping']['delivery_max_date'] !== $d['shipping']['delivery_min_date'] ? ' – '.\Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date'])->translatedFormat('D j M') : '')
        : null;
    $help = Route::has('storefront.support.index') ? route('storefront.support.index') : null;
    $pay = $d['status'] === 'pending_payment' && Route::has('storefront.checkout.pay') ? route('storefront.checkout.pay', ['order' => $d['uuid']]) : null;
@endphp
<x-sf::account.layout :title="__st('orders.details')" :crumb="__st('orders.title')" active="orders" page="order" :seo="$seo" :back="route('storefront.orders.index')">
    <x-slot:header>
        <div class="grid gap-3 lg:gap-5" data-order-header>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <h1 class="truncate text-lg font-semibold leading-[26px] text-sf-ink lg:text-[22px] lg:leading-[30px]" dir="ltr">{{ $d['number'] }}</h1>
                    <x-sf::order.badge :tone="$d['tone']">{{ $d['status_label'] }}</x-sf::order.badge>
                </div>
                <span class="hidden flex-1 lg:block"></span>
                <nav class="flex w-full gap-2 lg:w-auto" role="tablist" aria-label="{{ __st('orders.details') }}" data-order-tabs>
                    <a href="{{ $detailsUrl }}" role="tab" class="sf-pill-tab font-medium" aria-selected="{{ $panel === 'details' ? 'true' : 'false' }}" @if ($panel === 'details') aria-current="page" @endif data-order-tab="details">{{ __st('orders.tab_details') }}</a>
                    <a href="{{ $trackingUrl }}" role="tab" class="sf-pill-tab font-medium" aria-selected="{{ $panel === 'tracking' ? 'true' : 'false' }}" @if ($panel === 'tracking') aria-current="page" @endif data-order-tab="tracking">{{ __st('orders.tab_tracking') }}</a>
                </nav>
            </div>
            <p class="text-xs leading-4 text-sf-muted">{{ implode(' · ', $meta) }}</p>
        </div>
    </x-slot:header>

    @if ($cancelled)
        <section class="grid gap-2.5 rounded-lg bg-sf-danger-bg p-4" role="status" data-order-cancellation>
            <div class="flex items-start gap-2.5">
                <x-ui::icon name="x" :size="18" class="mt-0.5 shrink-0 text-sf-danger" />
                <div class="grid gap-0.5">
                    <h2 class="text-sm font-semibold leading-5 text-sf-danger">{{ __st('orders.cancelled_title') }}</h2>
                    @if ($cancelled['refund'])<p class="text-xs leading-4 text-sf-ink">{{ __st('orders.cancelled_refund_'.$cancelled['refund']) }}</p>@endif
                </div>
            </div>
            <dl class="grid gap-2 text-xs leading-4 sm:grid-cols-3">
                @if ($cancelled['reason'])<div><dt class="text-sf-muted">{{ __st('orders.cancel_reason') }}</dt><dd class="font-medium text-sf-ink">{{ $cancelled['reason'] }}</dd></div>@endif
                @if ($cancelled['at'])<div><dt class="text-sf-muted">{{ __st('orders.cancelled_on') }}</dt><dd class="font-medium text-sf-ink">{{ $cancelled['at']->translatedFormat('M j, Y H:i') }}</dd></div>@endif
                <div><dt class="text-sf-muted">{{ __st('orders.cancelled_by') }}</dt><dd class="font-medium text-sf-ink">{{ __st('orders.cancelled_by_'.$cancelled['by']) }}</dd></div>
            </dl>
        </section>
    @endif

    @if ($d['refunds'] !== [])
        <section class="sf-card grid gap-3 p-4" aria-labelledby="order-refunds" data-order-refunds>
            <h2 id="order-refunds" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('orders.refunds') }}</h2>
            <x-sf::order.refunds :refunds="$d['refunds']" />
        </section>
    @endif

    @if ($d['status'] === 'shipped')
        <p class="rounded-lg bg-sf-info-bg px-4 py-3 text-sm text-sf-info" role="status" data-order-shipped-notice>{{ __st('orders.shipped_notice') }}</p>
    @endif

    <div class="group/panels grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-5" data-order-panels data-panel="{{ $panel }}">
        <div class="grid min-w-0 gap-4">
            <section class="sf-card grid gap-3.5 p-4" aria-labelledby="order-items" data-order-items>
                <h2 id="order-items" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('orders.items_count', ['count' => count($d['items'])]) }}</h2>
                <ul class="grid gap-3.5">
                    @foreach ($d['items'] as $item)
                        <li class="flex items-center gap-3">
                            <x-sf::order.thumb :src="$item['image_url']" />
                            <div class="grid min-w-0 flex-1 gap-0.5">
                                <p class="truncate text-sm font-medium leading-5 text-sf-ink">{{ $item['name'] }}</p>
                                <p class="text-xs leading-4 text-sf-muted">{{ collect([$item['variant'], $unit($item)])->filter()->implode(' · ') }}@if ($item['reviewed']) · <span class="text-sf-success">{{ __st('orders.reviewed') }}</span>@endif</p>
                                @if ((int) $item['discount']['amount'] > 0)<p class="text-xs leading-4 text-sf-muted">{{ __st('orders.discount') }}: − {{ $money($item['discount']) }}</p>@endif
                                @php($state = $d['item_returns'][$item['id']] ?? null)
                                @if ($state && ($state['request'] || ($d['can_return'] && $state['returnable'])))
                                    <p class="flex flex-wrap items-center gap-2 pt-1">
                                        @if ($state['request'])
                                            <a href="{{ route('storefront.returns.show', $state['request']['id']) }}" class="hover:opacity-80" data-item-return><x-sf::order.badge :tone="$state['request']['tone']">{{ $state['request']['label'] }}</x-sf::order.badge></a>
                                        @endif
                                        @if ($d['can_return'] && $state['returnable'])
                                            <a href="{{ route('storefront.returns.create', ['order' => $d['uuid'], 'item' => $item['id']]) }}" class="text-xs font-medium text-sf-accent hover:underline" data-item-return-request>{{ __st('orders.request_item_return') }}@if ($state['available'] < $item['quantity']) ({{ __st('returns.left', ['count' => $state['available']]) }})@endif</a>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <p class="shrink-0 text-sm font-medium leading-5 text-sf-ink">{{ $money($item['total']) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="grid gap-3.5 text-sm leading-5">
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.items_subtotal') }}</dt><dd class="font-medium text-sf-ink">{{ $money($t['subtotal']) }}</dd></div>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.shipping') }}@if ($d['shipping']['company']) · {{ $d['shipping']['company'] }}@endif</dt><dd class="font-medium text-sf-ink">{{ (int) $t['shipping']['amount'] === 0 ? __st('orders.free') : $money($t['shipping']) }}</dd></div>
                    @if ($discount > 0)
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.discount') }}</dt><dd class="font-medium text-sf-ink">− {{ $money($t['flash_discount']) }}</dd></div>
                    @endif
                    @if ((int) $t['coupon_discount']['amount'] > 0)
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.coupon', ['code' => $d['coupon_code']]) }}</dt><dd class="font-medium text-sf-ink">− {{ $money($t['coupon_discount']) }}</dd></div>
                    @endif
                    @if ((int) $t['tax']['amount'] > 0)
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.tax') }}</dt><dd class="font-medium text-sf-ink">{{ $money($t['tax']) }}</dd></div>
                    @endif
                    <div class="flex items-center gap-3 text-sf-ink"><dt class="flex-1 text-base font-medium leading-6">{{ __st('orders.total') }}</dt><dd class="text-lg font-semibold leading-[26px]">{{ $money($t['grand_total']) }}</dd></div>
                </dl>
            </section>

            <section class="sf-card grid scroll-mt-28 gap-3.5 p-4 group-data-[panel=tracking]/panels:order-first" id="tracking" aria-labelledby="order-tracking" data-order-tracking>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2 id="order-tracking" class="flex-1 text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('orders.tracking') }}</h2>
                    @if ($d['carrier']['tracking'])
                        @php($awb = $d['carrier']['name'] ? __st('orders.awb', ['carrier' => $d['carrier']['name'], 'number' => $d['carrier']['tracking']]) : __st('orders.awb_only', ['number' => $d['carrier']['tracking']]))
                        @if ($d['carrier']['url'])
                            <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium leading-4 text-sf-accent hover:underline">{{ $awb }} ↗</a>
                        @else
                            <span class="text-xs font-medium leading-4 text-sf-accent">{{ $awb }}</span>
                        @endif
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-3 text-xs leading-4" data-tracking-meta>
                    <div><dt class="text-sf-muted">{{ __st('orders.carrier') }}</dt><dd class="font-medium text-sf-ink">{{ $d['carrier']['name'] ?: __st('orders.not_available') }}</dd></div>
                    <div><dt class="text-sf-muted">{{ __st('orders.estimated') }}</dt><dd class="font-medium text-sf-ink">{{ $eta ?? __st('orders.not_available') }}</dd></div>
                </dl>
                <x-sf::order.timeline :steps="$d['tracking']" />
                @if (collect($d['tracking'])->reject(fn (array $step) => $step['upcoming'] ?? false)->count() === 1 && ! $cancelled)
                    <p class="text-xs text-sf-muted">{{ __st('orders.tracking_empty') }}</p>
                @endif
            </section>

            @if ($d['activity'] !== [])
                <section class="sf-card grid gap-3 p-4" aria-labelledby="order-activity" data-order-activity>
                    <h2 id="order-activity" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('orders.activity') }}</h2>
                    <ul class="grid gap-2 text-sm leading-5">
                        @foreach ($d['activity'] as $entry)
                            <li class="flex items-baseline gap-3"><span class="min-w-0 flex-1 font-medium text-sf-ink">{{ $entry['label'] }}</span><span class="shrink-0 text-xs text-sf-muted">{{ $entry['at']?->translatedFormat('M j, Y H:i') }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="grid gap-4">
            <section class="sf-card grid gap-2 p-4">
                <h2 class="text-sm font-medium leading-5 text-sf-ink">{{ __st('orders.shipping_address') }}</h2>
                <x-sf::order.address :address="$d['shipping_address']" />
                <h2 class="text-sm font-medium leading-5 text-sf-ink">{{ __st('orders.billing_address') }}</h2>
                @if ($d['same_billing'])
                    <p class="text-xs leading-4 text-sf-muted">{{ __st('orders.same_as_shipping') }}</p>
                @else
                    <x-sf::order.address :address="$d['billing_address']" />
                @endif
            </section>

            <section class="sf-card grid gap-1 p-4 text-xs leading-4" data-order-payment>
                <h2 class="text-sm font-medium leading-5 text-sf-ink">{{ __st('orders.payment') }}</h2>
                <p class="text-sf-muted">{{ $d['payment_label'] ?: __st('orders.not_available') }}</p>
                <p class="text-sf-muted">{{ __st('orders.payment_status') }}: <span class="font-medium text-sf-ink">{{ $d['payment_status_label'] }}</span></p>
            </section>

            <section class="sf-card grid gap-2.5 p-4" data-order-actions>
                @if ($pay)
                    <a href="{{ $pay }}" class="sf-btn sf-btn-accent min-h-9 w-full px-4 text-xs"><x-ui::icon name="credit-card" :size="18" />{{ __st('orders.complete_payment') }}</a>
                @endif
                @if ($d['can_return'] && $d['returns_left'] > 0)
                    <a href="{{ route('storefront.returns.create', $d['uuid']) }}" class="sf-btn sf-btn-cta min-h-9 w-full px-4 text-xs"><x-ui::icon name="return" :size="18" class="rtl:-scale-x-100" />{{ __st('orders.request_return', ['count' => $d['returns_left']]) }}</a>
                @endif
                @if ($reviewItems->isNotEmpty())
                    <button type="button" class="sf-btn sf-btn-outline min-h-9 w-full px-4 text-xs" data-sheet-open="sf-write-review" data-review="{{ json_encode(['url' => route('store-api.v1.orders.items.review', ['order' => $d['uuid'], 'item' => '__ITEM__']), 'items' => $reviewItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="star" :size="18" />{{ __st('orders.write_review') }}</button>
                @endif
                <button type="button" class="sf-btn sf-btn-outline min-h-9 w-full px-4 text-xs" data-reorder="{{ json_encode(collect($d['items'])->map(fn ($item) => ['store_product_id' => $item['store_product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity'], 'name' => $item['name']])->values(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="refresh" :size="18" />{{ __st('orders.reorder') }}</button>
                <a href="{{ route('storefront.orders.invoice', $d['uuid']) }}" target="_blank" rel="noopener" class="sf-btn sf-btn-outline min-h-9 w-full px-4 text-xs"><x-ui::icon name="file" :size="18" />{{ __st('orders.download_invoice') }}</a>
                @if ($d['cancellable'])
                    <button type="button" class="sf-btn sf-btn-danger-outline min-h-9 w-full px-4 text-xs" data-sheet-open="sf-cancel-order" data-cancel-order="{{ json_encode(['url' => route('store-api.v1.orders.cancel', $d['uuid']), 'number' => $d['number'], 'paid' => $d['payment_status'] === 'paid' ? $money($t['grand_total']) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="x" :size="18" />{{ __st('orders.cancel_before_shipping') }}</button>
                @endif
            </section>
            @if ($help)
                <section class="sf-card grid gap-2 p-4" data-order-help>
                    <h2 class="text-sm font-medium leading-5 text-sf-ink">{{ __st('orders.help_title') }}</h2>
                    <p class="text-xs leading-4 text-sf-muted">{{ __st('orders.help_text') }}</p>
                    <a href="{{ $help }}" class="sf-btn sf-btn-outline min-h-9 w-full px-4 text-xs"><x-ui::icon name="lifebuoy" :size="18" />{{ __st('orders.contact_support') }}</a>
                </section>
            @endif
        </aside>
    </div>

    <x-sf::order.cancel-modal />
    <x-sf::order.review-modal />
</x-sf::account.layout>
