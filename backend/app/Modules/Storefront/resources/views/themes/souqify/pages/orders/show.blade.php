{{--
    Souqify order details ⇄ tracking. Details: the canonical Storefront additions page
    (100:104070 / m 100:104524, Souqify mode — the family-agnostic shared template).
    Tracking ($panel = tracking): the Souqify "On its way" page (Figma 1:75976 / m 1:80856,
    live tracking m 1:80997): status title + expected delivery, Cancel order / Support actions,
    four-step progress (confirmed → processing → in transit → delivered) with the current step
    glowing, "Tracking History" (newest first: time, day, title, note), carrier tracking card,
    shipping address and package details.
    Inputs as the Ecommet view: $detail, $panel, $seo.
--}}
@if (($panel ?? 'details') !== 'tracking')
    @include('storefront::themes.ecommet.pages.orders.show')
@else
    @php
        $money = fn ($amount) => \App\Modules\Storefront\Services\Account\AccountOrderPages::money($amount);
        $d = $detail;
        $address = is_array($d['shipping_address'] ?? null) ? $d['shipping_address'] : [];
        $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? ($address['country_iso2'] ?? null);
        $status = $d['status'];
        $delivered = (bool) $d['delivered_at'] || in_array($status, ['delivered', 'completed'], true);
        $cancelled = in_array($status, ['cancelled', 'refunded', 'returned'], true);
        $shipped = $delivered || $status === 'shipped';
        $processing = $shipped || in_array($status, ['paid', 'processing'], true);
        $reached = $delivered ? 4 : ($shipped ? 3 : ($processing ? 2 : 1));
        $milestones = [
            ['key' => 'confirmed', 'icon' => 'check', 'label' => __st('souqify_pages.tracking.steps.confirmed')],
            ['key' => 'processing', 'icon' => 'check', 'label' => __st('souqify_pages.tracking.steps.processing')],
            ['key' => 'transit', 'icon' => 'truck', 'label' => __st('souqify_pages.tracking.steps.transit')],
            ['key' => 'delivered', 'icon' => 'home', 'label' => __st('souqify_pages.tracking.steps.delivered')],
        ];
        $title = $cancelled ? $d['status_label'] : ($delivered ? __st('souqify_pages.tracking.title_delivered') : ($shipped ? __st('souqify_pages.tracking.title_transit') : __st('souqify_pages.tracking.title_preparing')));
        $min = $d['shipping']['delivery_min_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date']) : null;
        $max = $d['shipping']['delivery_max_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date']) : null;
        $contact = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => 'contact']) : null;
        $units = array_sum(array_map(fn ($item) => (int) $item['quantity'], $d['items']));
    @endphp
    <x-sf::layout :title="__st('souqify_pages.tracking.title')" :mobile-title="__st('souqify_pages.tracking.title')" :back="route('storefront.orders.show', $d['uuid'])" nav="account" page="order-tracking" :seo="$seo">
        <div class="sq-wrap grid gap-6 pb-16 pt-4 lg:pt-6" data-sf-account>
            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="hidden lg:block">
                <ol class="sq-crumbs">
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.orders.index') }}">{{ __st('orders.title') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.orders.show', $d['uuid']) }}" dir="ltr">{{ $d['number'] }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                    <li><span aria-current="page">{{ __st('souqify_pages.tracking.title') }}</span></li>
                </ol>
            </nav>

            <header class="flex flex-wrap items-end justify-between gap-4" data-reveal="fade-up">
                <div class="grid gap-2">
                    <h1 class="text-2xl font-medium text-[#242424] lg:text-[32px]">{{ $title }}</h1>
                    <p class="text-xs text-sf-muted">
                        <span dir="ltr">{{ $d['number'] }}</span>
                        @if ($delivered && $d['delivered_at'])
                            · {{ __st('orders.delivered_on', ['date' => $d['delivered_at']->translatedFormat('D, M j')]) }}
                        @elseif (! $cancelled && $min)
                            · {{ __st('souqify_pages.tracking.expected') }}: <span class="text-sq-primary">{{ $min->translatedFormat('D, M j') }}@if ($max && ! $max->isSameDay($min)) – {{ $max->translatedFormat('D, M j') }}@endif</span>
                        @endif
                    </p>
                </div>
                <div class="flex w-full flex-wrap gap-3 sm:w-auto">
                    @if ($d['cancellable'])
                        <button type="button" class="sq-btn sq-btn-danger mo-press h-12 flex-1 px-6 sm:flex-none" data-sheet-open="sf-cancel-order" data-cancel-order="{{ json_encode(['url' => route('store-api.v1.orders.cancel', $d['uuid']), 'number' => $d['number'], 'paid' => $d['payment_status'] === 'paid' ? $money($d['totals']['grand_total']) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="trash" :size="18" />{{ __st('orders.cancel') }}</button>
                    @endif
                    @if ($contact)
                        <a href="{{ $contact }}" class="sq-btn sq-btn-primary mo-press h-12 flex-1 px-8 sm:flex-none"><x-ui::icon name="mail" :size="18" />{{ __st('souqify_pages.tracking.support') }}</a>
                    @endif
                </div>
            </header>

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_396px]">
                <section class="sq-card grid gap-8 p-4 lg:p-6" aria-labelledby="sq-history-title" id="tracking" data-reveal="fade-up">
                    @unless ($cancelled)
                        <ol class="sq-steps" role="list" aria-label="{{ __st('souqify_pages.tracking.progress') }}">
                            @foreach ($milestones as $index => $step)
                                @php($state = $index + 1 < $reached || ($index + 1 === $reached && $delivered) ? 'done' : ($index + 1 === $reached ? 'current' : 'pending'))
                                <li class="sq-step" data-state="{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                                    <span class="sq-step-dot" aria-hidden="true"><x-ui::icon :name="$state === 'pending' ? $step['icon'] : ($state === 'current' ? $step['icon'] : 'check')" :size="16" /></span>
                                    <span class="sq-step-label">{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endunless

                    <div class="grid gap-5">
                        <h2 id="sq-history-title" class="text-base font-semibold text-sq-ink">{{ __st('souqify_pages.tracking.history') }}</h2>
                        <ol class="grid" data-reveal-stagger>
                            @foreach ($d['tracking'] as $event)
                                @php($latest = $loop->first && ! $delivered && ! $cancelled)
                                <li class="sq-history" data-state="{{ $latest ? 'current' : 'done' }}" data-reveal="fade-up">
                                    <span class="grid content-start text-end">
                                        <span class="text-sm font-medium text-sq-ink">{{ $event['at']?->translatedFormat('H:i') }}</span>
                                        <span class="text-[10px] uppercase tracking-wide text-sf-muted">{{ $event['at']?->isToday() ? __st('souqify_pages.tracking.today') : ($event['at']?->isYesterday() ? __st('souqify_pages.tracking.yesterday') : $event['at']?->translatedFormat('M j')) }}</span>
                                    </span>
                                    <span class="sq-history-dot" aria-hidden="true"></span>
                                    <span class="grid gap-1 pb-6">
                                        <span class="text-sm font-semibold text-sq-ink">{{ $event['label'] === '__placed' ? __st('orders.tracking_placed') : $event['label'] }}</span>
                                        @if ($latest)
                                            <span class="text-xs text-sf-muted">{{ $shipped ? __st('souqify_pages.tracking.in_transit') : __st('souqify_pages.tracking.preparing') }}</span>
                                        @endif
                                        @if ($event['note'])
                                            <span class="text-xs {{ $latest ? 'text-sq-primary' : 'text-sf-muted' }}">{{ $event['note'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                        @if (count($d['tracking']) === 1)
                            <p class="text-xs text-sf-muted">{{ __st('orders.tracking_empty') }}</p>
                        @endif
                    </div>
                </section>

                <aside class="grid gap-4" data-reveal-stagger>
                    <section class="sq-card grid gap-4 p-4" data-reveal="fade-up" aria-labelledby="sq-live-title">
                        <div class="sq-live-art" aria-hidden="true"><x-ui::icon name="map" :size="44" /></div>
                        <h2 id="sq-live-title" class="text-sm font-semibold text-sq-ink">{{ __st('souqify_pages.tracking.live') }}</h2>
                        @if ($d['carrier']['url'])
                            <p class="text-xs text-sf-muted">{{ __st('souqify_pages.tracking.live_body') }}</p>
                            <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="sq-btn sq-btn-primary mo-press h-12 w-full"><x-ui::icon name="map-pin" :size="18" />{{ __st('souqify_pages.tracking.open_carrier', ['carrier' => $d['carrier']['name'] ?? '']) }}</a>
                        @else
                            <p class="text-xs text-sf-muted">{{ __st('souqify_pages.tracking.live_pending') }}</p>
                        @endif
                    </section>
                    <section class="sq-card grid gap-5 p-4 lg:p-5" data-reveal="fade-up">
                        <div class="grid gap-3">
                            <h2 class="text-sm font-medium uppercase text-sq-ink">{{ __st('souqify_pages.tracking.address') }}</h2>
                            <p class="flex items-center gap-2 text-sm font-semibold text-sq-ink"><x-ui::icon name="map-pin" :size="16" class="text-sq-primary" />{{ $address['full_name'] ?? '' }}</p>
                            <p class="ps-6 text-xs leading-relaxed text-sf-muted">{{ collect([$address['address_line1'] ?? null, $address['address_line2'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ') ?: '—' }}</p>
                            @if (! empty($address['phone']))
                                <p class="ps-6 text-xs text-sf-muted"><span dir="ltr">{{ $address['phone'] }}</span></p>
                            @endif
                        </div>
                        <div class="grid gap-3 border-t border-sq-line pt-4">
                            <h2 class="text-sm font-medium uppercase text-sq-ink">{{ __st('souqify_pages.tracking.package') }}</h2>
                            <dl class="grid gap-2 text-xs">
                                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('souqify_pages.tracking.carrier') }}</dt><dd class="font-semibold text-sq-ink">{{ $d['carrier']['name'] ?: ($d['shipping']['company'] ?? '—') }}</dd></div>
                                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('souqify_pages.tracking.number') }}</dt><dd class="font-semibold text-sq-ink" dir="ltr">{{ $d['carrier']['tracking'] ?: '—' }}</dd></div>
                                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('souqify_pages.tracking.items') }}</dt><dd class="font-semibold text-sq-ink">{{ __st('orders.qty', ['count' => $units]) }}</dd></div>
                                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.total') }}</dt><dd class="font-semibold text-sq-ink">{{ $money($d['totals']['grand_total']) }}</dd></div>
                            </dl>
                            <ul class="grid gap-3 pt-1" role="list">
                                @foreach ($d['items'] as $item)
                                    <li class="flex items-center gap-3">
                                        <x-sf::order.thumb :src="$item['image_url']" :size="44" />
                                        <span class="grid min-w-0 gap-0.5 text-xs">
                                            <span class="truncate text-sq-ink">{{ $item['name'] }}</span>
                                            <span class="text-sf-muted">{{ collect([$item['variant'], __st('orders.qty', ['count' => $item['quantity']])])->filter()->implode(' · ') }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <a href="{{ route('storefront.orders.show', $d['uuid']) }}" class="sq-btn sq-btn-outline mo-press h-11 w-full rounded-full">{{ __st('orders.view_details') }}</a>
                    </section>
                </aside>
            </div>
        </div>
        @if ($d['cancellable'])
            <x-sf::order.cancel-modal />
        @endif
    </x-sf::layout>
@endif
