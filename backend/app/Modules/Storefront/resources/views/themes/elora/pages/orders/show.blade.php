{{--
    Elora order details ⇄ tracking.
    Details (client Figma 1:18987, phone m 1:23600): header (back, "Order #…", status,
    Download Invoice), Items Ordered, Shipping Information, Payment Summary and the action
    pills. Everything the Ecommet details page has and the frames do not draw (cancellation,
    refunds, returns per line, timeline, activity, billing address, pay, return, cancel,
    help) is kept in the same card language.
    Tracking (1:19981 / m 1:23708, live m 1:20213): header card, shipping timeline, delivery
    address, product details, carrier card, help; phones get the product card first and the
    fixed "Live Tracking" bar.
    Inputs as the Ecommet view: $detail, $panel, $seo.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $d = $detail;
    $address = is_array($d['shipping_address'] ?? null) ? $d['shipping_address'] : [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? ($address['country_iso2'] ?? null);
    $line = collect([$address['address_line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ');
    $detailsUrl = route('storefront.orders.show', $d['uuid']);
    $trackingUrl = route('storefront.orders.tracking', $d['uuid']);
    $help = Route::has('storefront.support.index') ? route('storefront.support.index') : null;
@endphp
@if (($panel ?? 'details') !== 'tracking')
    @php
        $t = $d['totals'];
        $reviewItems = collect($d['items'])->where('reviewable', true)->map(fn ($item) => ['id' => $item['id'], 'name' => $item['name'], 'image' => $item['image_url'], 'variant' => $item['variant']])->values();
        $discount = (int) $t['flash_discount']['amount'];
        $meta = implode(' · ', array_filter([
            __st('orders.placed_on', ['date' => $d['placed']?->translatedFormat('M j, Y')]),
            $d['delivered_at'] ? __st('orders.delivered_on', ['date' => $d['delivered_at']->translatedFormat('M j, Y')]) : null,
        ]));
        $cancelled = $d['cancellation'] ?? null;
        $unit = fn (array $item) => $item['quantity'] > 1 ? __st('orders.qty_price', ['count' => $item['quantity'], 'price' => $money($item['unit_price'])]) : __st('orders.qty', ['count' => $item['quantity']]);
        $eta = $d['shipping']['delivery_min_date']
            ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date'])->translatedFormat('D j M').($d['shipping']['delivery_max_date'] && $d['shipping']['delivery_max_date'] !== $d['shipping']['delivery_min_date'] ? ' – '.\Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date'])->translatedFormat('D j M') : '')
            : null;
        $pay = $d['status'] === 'pending_payment' && Route::has('storefront.checkout.pay') ? route('storefront.checkout.pay', ['order' => $d['uuid']]) : null;
        $invoice = route('storefront.orders.invoice', $d['uuid']);
        $number = __st('elora_pages.account.order_title', ['number' => $d['number']]);
    @endphp
    <x-sf::elora.account.layout :title="$number" :crumb="__st('account.nav.orders')" active="orders" page="order" :seo="$seo" order-tab="all" :tabs="false" :heading="false" :back="route('storefront.orders.index')">
        <x-slot:mobileHead>
            <div class="eac-mhead-row">
                <a href="{{ route('storefront.orders.index') }}" class="eac-back mo-press" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="elo-back-chevron" :size="24" /></a>
                <div class="min-w-0 flex-1">
                    <p class="eac-mhead-title" dir="ltr">#{{ $d['number'] }}</p>
                    <p class="eac-mhead-sub">{{ $meta }}</p>
                </div>
                <a href="{{ $invoice }}" target="_blank" rel="noopener" class="eac-round mo-press" aria-label="{{ __st('orders.download_invoice') }}"><x-ui::icon name="elo-download" :size="20" /></a>
            </div>
        </x-slot:mobileHead>

        <header class="eac-detail-head" data-order-header>
            <a href="{{ route('storefront.orders.index') }}" class="eac-detail-back mo-press" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="elo-back" :size="20" /></a>
            <div class="min-w-0">
                <p class="eac-detail-number" dir="auto">{{ $number }}</p>
                <p class="eac-detail-date">{{ $meta }}</p>
            </div>
            <x-sf::elora.account.status :tone="$d['tone']">{{ $d['status_label'] }}</x-sf::elora.account.status>
            <a href="{{ $invoice }}" target="_blank" rel="noopener" class="eac-invoice mo-press"><x-ui::icon name="elo-download" :size="20" />{{ __st('orders.download_invoice') }}</a>
        </header>

        <div class="eac-detail">
            <div class="eac-col-main">
                @if ($cancelled)
                    <section class="eac-banner eac-card eac-card-danger" role="status" data-order-cancellation>
                        <h2 class="eac-card-title text-sf-danger"><x-ui::icon name="x" :size="20" />{{ __st('orders.cancelled_title') }}</h2>
                        @if ($cancelled['refund'])<p class="text-sm text-sf-ink">{{ __st('orders.cancelled_refund_'.$cancelled['refund']) }}</p>@endif
                        <dl class="eac-rows">
                            @if ($cancelled['reason'])<div><dt>{{ __st('orders.cancel_reason') }}</dt><dd>{{ $cancelled['reason'] }}</dd></div>@endif
                            @if ($cancelled['at'])<div><dt>{{ __st('orders.cancelled_on') }}</dt><dd>{{ $cancelled['at']->translatedFormat('M j, Y H:i') }}</dd></div>@endif
                            <div><dt>{{ __st('orders.cancelled_by') }}</dt><dd>{{ __st('orders.cancelled_by_'.$cancelled['by']) }}</dd></div>
                        </dl>
                    </section>
                @endif

                @if ($d['status'] === 'shipped')
                    <p class="eac-banner rounded-lg bg-sf-info-bg px-4 py-3 text-sm text-sf-info" role="status" data-order-shipped-notice>{{ __st('orders.shipped_notice') }}</p>
                @endif

                <section class="eac-card eac-items" aria-labelledby="order-items" data-order-items data-reveal="fade-up">
                    <div class="eac-items-head">
                        <h2 id="order-items">{{ __st('elora_pages.account.items_ordered') }}</h2>
                        <x-sf::elora.account.status :tone="$d['tone']" data-order-status class="eac-items-status">{{ $d['status_label'] }}</x-sf::elora.account.status>
                    </div>
                    <ul class="eac-lines">
                        @foreach ($d['items'] as $item)
                            <li class="eac-line">
                                <x-sf::elora.account.thumbs :images="[$item['image_url']]" />
                                <div class="eac-line-text">
                                    <p class="eac-line-name" dir="auto">{{ $item['name'] }}</p>
                                    <p class="eac-line-meta">{{ collect([$item['variant'], $unit($item)])->filter()->implode(' · ') }}@if ($item['reviewed']) · <span class="text-sf-success">{{ __st('orders.reviewed') }}</span>@endif</p>
                                    <p class="eac-line-price">{{ $money($item['total']) }}</p>
                                    @if ((int) $item['discount']['amount'] > 0)<p class="eac-line-meta">{{ __st('orders.discount') }}: − {{ $money($item['discount']) }}</p>@endif
                                    @php $state = $d['item_returns'][$item['id']] ?? null; @endphp
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
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="eac-card eac-shipping" aria-labelledby="order-shipping" data-reveal="fade-up">
                    <h2 id="order-shipping" class="eac-card-title"><x-ui::icon name="elo-pin" :size="20" />{{ __st('elora_pages.account.shipping_info') }}</h2>
                    <div class="eac-address">
                        <p dir="auto">{{ $line ?: '—' }}</p>
                        @if (! empty($address['full_name']) || ! empty($address['phone']))
                            <p>{{ $address['full_name'] ?? '' }}@if (! empty($address['phone'])) · <span dir="ltr">{{ $address['phone'] }}</span>@endif</p>
                        @endif
                    </div>
                    <dl class="eac-rows eac-rows-top">
                        <div><dt>{{ __st('orders.carrier') }}</dt><dd>{{ $d['carrier']['name'] ?: ($d['shipping']['company'] ?: __st('orders.not_available')) }}</dd></div>
                        <div><dt>{{ __st('elora_pages.tracking.number') }}</dt><dd dir="ltr">
                            @if ($d['carrier']['tracking'] && $d['carrier']['url'])
                                <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="text-elo-primary hover:underline">{{ $d['carrier']['tracking'] }}</a>
                            @else
                                {{ $d['carrier']['tracking'] ?: __st('orders.not_available') }}
                            @endif
                        </dd></div>
                        <div><dt>{{ __st('orders.estimated') }}</dt><dd>{{ $eta ?? __st('orders.not_available') }}</dd></div>
                    </dl>
                    <div class="eac-billing">
                        <h3>{{ __st('orders.billing_address') }}</h3>
                        @if ($d['same_billing'])
                            <p class="eac-address">{{ __st('orders.same_as_shipping') }}</p>
                        @else
                            <x-sf::order.address :address="$d['billing_address']" />
                        @endif
                    </div>
                </section>

                @if ($d['refunds'] !== [])
                    <section class="eac-card eac-refunds" aria-labelledby="order-refunds" data-order-refunds data-reveal="fade-up">
                        <h2 id="order-refunds" class="eac-card-title">{{ __st('orders.refunds') }}</h2>
                        <x-sf::order.refunds :refunds="$d['refunds']" />
                    </section>
                @endif

                <section class="eac-card eac-timeline scroll-mt-28" id="tracking" aria-labelledby="order-tracking" data-order-tracking data-reveal="fade-up">
                    <h2 id="order-tracking" class="eac-card-title">{{ __st('orders.tracking') }}</h2>
                    <x-sf::order.timeline :steps="$d['tracking']" />
                    @if (collect($d['tracking'])->reject(fn (array $step) => $step['upcoming'] ?? false)->count() === 1 && ! $cancelled)
                        <p class="text-xs text-sf-muted">{{ __st('orders.tracking_empty') }}</p>
                    @endif
                </section>

                @if ($d['activity'] !== [])
                    <section class="eac-card eac-activity" aria-labelledby="order-activity" data-order-activity data-reveal="fade-up">
                        <h2 id="order-activity" class="eac-card-title">{{ __st('orders.activity') }}</h2>
                        <dl class="eac-rows">
                            @foreach ($d['activity'] as $entry)
                                <div><dt>{{ $entry['at']?->translatedFormat('M j, Y H:i') }}</dt><dd>{{ $entry['label'] }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>

            <aside class="eac-col-side">
                <section class="eac-card eac-payment" aria-labelledby="order-payment" data-order-payment data-reveal="fade-up">
                    <h2 id="order-payment" class="eac-card-title"><x-ui::icon name="elo-pay-card" :size="20" />{{ __st('elora_pages.account.payment_summary') }}</h2>
                    <dl class="eac-sum">
                        <div><dt>{{ __st($d['has_fixed_shipping'] ? 'orders.items_subtotal' : 'orders.items_subtotal_plain') }}</dt><dd>{{ $money($t['subtotal']) }}</dd></div>
                        <div><dt>{{ __st('orders.shipping') }}@if ($d['shipping']['company']) · {{ $d['shipping']['company'] }}@endif</dt><dd>{{ (int) $t['shipping']['amount'] === 0 ? __st('orders.free') : $money($t['shipping']) }}</dd></div>
                        @if ($discount > 0)<div><dt>{{ __st('orders.discount') }}</dt><dd>− {{ $money($t['flash_discount']) }}</dd></div>@endif
                        @if ((int) $t['coupon_discount']['amount'] > 0)<div><dt>{{ __st('orders.coupon', ['code' => $d['coupon_code']]) }}</dt><dd>− {{ $money($t['coupon_discount']) }}</dd></div>@endif
                        @if ((int) $t['tax']['amount'] > 0)<div><dt>{{ __st('orders.tax') }}</dt><dd>{{ $money($t['tax']) }}</dd></div>@endif
                        <div class="eac-sum-total"><dt>{{ __st('orders.total') }}</dt><dd>{{ $money($t['grand_total']) }}</dd></div>
                    </dl>
                    <dl class="eac-sum eac-sum-method">
                        <div><dt>{{ __st('elora_pages.account.payment_method') }}</dt><dd>{{ $d['payment_label'] ?: __st('orders.not_available') }}</dd></div>
                        <div><dt>{{ __st('orders.payment_status') }}</dt><dd @class(['text-[#00a63e]' => $d['payment_status'] === 'paid'])>{{ $d['payment_status_label'] }}</dd></div>
                    </dl>
                </section>

                <section class="eac-actions" data-order-actions data-reveal="fade-up">
                    @if ($pay)
                        <a href="{{ $pay }}" class="eac-pill eac-pill-primary mo-press"><x-ui::icon name="elo-pay-card" :size="20" />{{ __st('orders.complete_payment') }}</a>
                    @endif
                    @unless ($cancelled)
                        <a href="{{ $trackingUrl }}" class="eac-pill eac-pill-ink mo-press"><x-ui::icon name="elo-track-truck" :size="20" />{{ __st('elora_pages.account.track') }}</a>
                    @endunless
                    <button type="button" class="eac-pill mo-press" data-reorder="{{ json_encode(collect($d['items'])->map(fn ($item) => ['store_product_id' => $item['store_product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity'], 'name' => $item['name']])->values(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="elo-reorder" :size="20" />{{ __st('orders.reorder') }}</button>
                    @if ($reviewItems->isNotEmpty())
                        <button type="button" class="eac-pill eac-pill-review mo-press" data-sheet-open="sf-write-review" data-review="{{ json_encode(['url' => route('store-api.v1.orders.items.review', ['order' => $d['uuid'], 'item' => '__ITEM__']), 'items' => $reviewItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="elo-star-line" :size="20" />{{ __st('orders.write_review') }}</button>
                    @endif
                    @if ($d['can_return'] && $d['returns_left'] > 0)
                        <a href="{{ route('storefront.returns.create', $d['uuid']) }}" class="eac-pill mo-press"><x-ui::icon name="return" :size="20" />{{ __st('orders.request_return', ['count' => $d['returns_left']]) }}</a>
                    @endif
                    @if ($d['cancellable'])
                        <button type="button" class="eac-pill eac-pill-danger mo-press" data-sheet-open="sf-cancel-order" data-cancel-order="{{ json_encode(['url' => route('store-api.v1.orders.cancel', $d['uuid']), 'number' => $d['number'], 'paid' => $d['payment_status'] === 'paid' ? $money($t['grand_total']) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.cancel_before_shipping') }}</button>
                    @endif
                </section>

                @if ($help)
                    <section class="eac-card eac-help" data-order-help data-reveal="fade-up">
                        <h2 class="eac-card-title">{{ __st('orders.help_title') }}</h2>
                        <p class="eac-address">{{ __st('orders.help_text') }}</p>
                        <a href="{{ $help }}" class="eac-pill eac-pill-sm mo-press">{{ __st('orders.contact_support') }}</a>
                    </section>
                @endif
            </aside>
        </div>

        <x-sf::order.cancel-modal />
        <x-sf::order.review-modal />
    </x-sf::elora.account.layout>
@else
    @php
        $events = array_reverse($d['tracking']);
        $events = array_values(array_filter($events, fn (array $step): bool => ! ($step['upcoming'] ?? false)));
        $delivered = (bool) $d['delivered_at'] || in_array($d['status'], ['delivered', 'completed'], true);
        $cancelled = in_array($d['status'], ['cancelled', 'refunded', 'returned'], true);
        $shipped = $delivered || $d['status'] === 'shipped';
        $min = $d['shipping']['delivery_min_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date']) : null;
        $max = $d['shipping']['delivery_max_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date']) : null;
        $upcoming = [];
        if (! $cancelled && ! $delivered) {
            if (! $shipped) {
                $upcoming[] = ['label' => __st('orders.steps.shipped'), 'at' => null];
            }
            $upcoming[] = ['label' => __st('orders.steps.delivered'), 'at' => $max ?? $min];
        }
        $contact = $help ?? (Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => 'contact']) : null);
    @endphp
    <x-sf::layout :title="__st('elora_pages.tracking.title')" nav="orders" page="order-tracking" :seo="$seo">
        <div class="eac eac-track" data-elo-account="order-tracking" data-sf-account>
            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="eac-crumbs">
                <ol>
                    <li><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                    @if (Route::has('storefront.account.profile'))<li><a href="{{ route('storefront.account.profile') }}">{{ __st('elora_pages.account.crumb') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>@endif
                    <li><a href="{{ route('storefront.orders.index') }}">{{ __st('account.nav.orders') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                    <li><span aria-current="page">{{ __st('elora_pages.account.track') }}</span></li>
                </ol>
            </nav>

            <div class="eac-mhead">
                <div class="eac-mhead-row">
                    <a href="{{ $detailsUrl }}" class="eac-back mo-press" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="elo-back-chevron" :size="24" /></a>
                    <div class="min-w-0 flex-1">
                        <p class="eac-mhead-title">{{ __st('elora_pages.success.tracking') }}</p>
                        <p class="eac-mhead-sub" dir="ltr">{{ $d['carrier']['tracking'] ?: $d['number'] }}</p>
                    </div>
                    <span class="eac-transit"><x-ui::icon name="box" :size="18" />{{ $d['status_label'] }}</span>
                </div>
            </div>

            <section class="eac-card eac-track-head" data-reveal="fade-up" aria-labelledby="elo-track-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 id="elo-track-title">{{ __st('elora_pages.tracking.title') }}</h1>
                        <p class="eac-track-order"><a href="{{ $detailsUrl }}" class="hover:text-elo-primary">{{ __st('elora_pages.account.order_title', ['number' => $d['number']]) }}</a></p>
                    </div>
                    <span class="eac-transit"><x-ui::icon name="box" :size="18" />{{ $d['status_label'] }}</span>
                </div>
                <dl class="eac-track-meta">
                    <div><dt>{{ __st('elora_pages.tracking.number') }}</dt><dd dir="ltr">{{ $d['carrier']['tracking'] ?: '—' }}</dd></div>
                    <div><dt>{{ __st('elora_pages.tracking.carrier') }}</dt><dd>{{ $d['carrier']['name'] ?: ($d['shipping']['company'] ?? '—') }}</dd></div>
                    <div><dt>{{ $delivered ? __st('orders.steps.delivered') : __st('elora_pages.tracking.expected') }}</dt>
                        <dd class="text-elo-primary">
                            @if ($delivered && $d['delivered_at'])
                                {{ $d['delivered_at']->translatedFormat('M j, Y') }}
                            @elseif ($min)
                                {{ $min->translatedFormat('M j') }}@if ($max && ! $max->isSameDay($min)) – {{ $max->translatedFormat('M j, Y') }}@else{{ $min->translatedFormat(', Y') }}@endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <div class="eac-track-grid">
                <section class="eac-card eac-track-line" aria-labelledby="elo-timeline-title" id="tracking">
                    <h2 id="elo-timeline-title">{{ __st('elora_pages.tracking.timeline') }}</h2>
                    <ol class="grid" data-reveal-stagger>
                        @foreach ($events as $step)
                            @php $current = $loop->last && ! $delivered && $upcoming !== []; @endphp
                            <li class="elo-track-step" data-state="{{ $current ? 'current' : 'done' }}" data-reveal="fade-up" @if ($current) aria-current="step" @endif>
                                <span class="elo-track-dot" aria-hidden="true"><x-ui::icon name="check" :size="12" /></span>
                                <span class="elo-track-label text-sm font-medium text-elo-ink">{{ $step['label'] === '__placed' ? __st('orders.tracking_placed') : $step['label'] }}</span>
                                <span class="text-end text-xs text-sf-muted">{{ $step['at']?->translatedFormat('M j, Y') }}<br>{{ $step['at']?->translatedFormat('H:i') }}</span>
                                @if ($step['note'])
                                    <span class="col-start-2 flex items-center gap-1 text-xs text-sf-muted"><x-ui::icon name="map-pin" :size="12" />{{ $step['note'] }}</span>
                                @endif
                                @if ($current)
                                    <p class="col-span-2 col-start-2 mt-2 grid gap-1 rounded-md border border-elo-primary bg-[var(--elo-tint-strong)] p-3 text-xs text-elo-ink">
                                        <span class="flex items-center gap-1.5 font-medium text-elo-primary"><x-ui::icon name="truck" :size="14" />{{ __st('elora_pages.tracking.current') }}</span>
                                        {{ $shipped ? __st('elora_pages.tracking.in_transit') : __st('elora_pages.tracking.preparing') }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                        @foreach ($upcoming as $step)
                            <li class="elo-track-step" data-state="pending" data-reveal="fade-up">
                                <span class="elo-track-dot" aria-hidden="true"><span class="size-2 rounded-full bg-current"></span></span>
                                <span class="elo-track-label text-sm font-medium">{{ $step['label'] }}</span>
                                <span class="text-end text-xs text-sf-muted">@if ($step['at']){{ $step['at']->translatedFormat('M j, Y') }}<br>@endif{{ __st('elora_pages.tracking.expected_short') }}</span>
                            </li>
                        @endforeach
                    </ol>
                    @if (count($events) === 1)
                        <p class="text-xs text-sf-muted">{{ __st('orders.tracking_empty') }}</p>
                    @endif
                </section>

                <aside class="eac-track-side" data-reveal-stagger>
                    <section class="eac-card eac-track-address" data-reveal="fade-up">
                        <h2><x-ui::icon name="home" :size="20" />{{ __st('elora_pages.tracking.address') }}</h2>
                        <p class="font-medium text-elo-ink" dir="auto">{{ $address['full_name'] ?? '' }}</p>
                        <p dir="auto">{{ $line ?: '—' }}</p>
                        @if (! empty($address['phone']))
                            <p>{{ __st('elora_pages.tracking.phone') }} <span dir="ltr">{{ $address['phone'] }}</span></p>
                        @endif
                    </section>
                    <section class="eac-card eac-track-products" data-reveal="fade-up">
                        <h2>{{ __st('elora_pages.tracking.products') }}</h2>
                        <ul class="eac-lines" role="list">
                            @foreach ($d['items'] as $item)
                                <li class="eac-line">
                                    <x-sf::elora.account.thumbs :images="[$item['image_url']]" />
                                    <div class="eac-line-text">
                                        <p class="eac-line-name" dir="auto">{{ $item['name'] }}</p>
                                        <p class="eac-line-meta">{{ __st('orders.qty', ['count' => $item['quantity']]) }}</p>
                                        <p class="eac-line-price">{{ $money($item['total']) }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                    <section class="eac-card eac-track-live" data-reveal="fade-up">
                        <h2>{{ __st('elora_pages.tracking.live') }}</h2>
                        @if ($d['carrier']['url'])
                            <p>{{ __st('elora_pages.tracking.live_body') }}</p>
                            <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="elo-btn elo-btn-outline mo-press w-full"><x-ui::icon name="map" :size="18" />{{ __st('elora_pages.tracking.open_carrier', ['carrier' => $d['carrier']['name'] ?? '']) }}</a>
                        @else
                            <p>{{ __st('elora_pages.tracking.live_pending') }}</p>
                        @endif
                    </section>
                    <section class="eac-track-help" data-reveal="fade-up">
                        <h2>{{ __st('elora_pages.tracking.help') }}</h2>
                        <p>{{ __st('elora_pages.tracking.help_body') }}</p>
                        @if ($contact)
                            <a href="{{ $contact }}" class="elo-btn elo-btn-ink mo-press w-full">{{ __st('elora_pages.tracking.contact') }}</a>
                        @endif
                    </section>
                </aside>
            </div>

            @if ($d['carrier']['url'])
                <div class="eac-livebar">
                    <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="mo-press"><span aria-hidden="true"></span>{{ __st('elora_pages.tracking.live') }}</a>
                </div>
            @endif
        </div>
    </x-sf::layout>
@endif
