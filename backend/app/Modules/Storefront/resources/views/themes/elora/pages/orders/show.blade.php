{{--
    Elora order details ⇄ tracking. Details: the canonical Storefront additions page
    (100:103150 / m 100:103617, Elora mode). Tracking ($panel = tracking): the Elora "Track
    your order" page (Figma 1:19981 / m 1:23708, live tracking m 1:20213): header card
    (order, tracking number, carrier, expected delivery, status pill), shipping timeline
    (done events oldest → newest, current step highlighted with a pulsing dot, upcoming
    milestones greyed), delivery address, product details, carrier tracking card and help.
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
        $events = array_reverse($d['tracking']);
        $delivered = (bool) $d['delivered_at'] || in_array($d['status'], ['delivered', 'completed'], true);
        $cancelled = in_array($d['status'], ['cancelled', 'refunded', 'returned'], true);
        $shipped = $delivered || in_array($d['status'], ['shipped'], true);
        $min = $d['shipping']['delivery_min_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date']) : null;
        $max = $d['shipping']['delivery_max_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date']) : null;
        $upcoming = [];
        if (! $cancelled && ! $delivered) {
            if (! $shipped) {
                $upcoming[] = ['label' => __st('orders.steps.shipped'), 'at' => null];
            }
            $upcoming[] = ['label' => __st('orders.steps.delivered'), 'at' => $max ?? $min];
        }
        $contact = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => 'contact']) : null;
    @endphp
    <x-sf::layout :title="__st('elora_pages.tracking.title')" :mobile-title="__st('elora_pages.tracking.title')" :back="route('storefront.orders.show', $d['uuid'])" nav="account" page="order-tracking" :seo="$seo">
        <div class="elo-narrow grid gap-5 pb-16 pt-4 lg:pt-6">
            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="hidden lg:block">
                <ol class="elo-crumbs">
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.home') }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                    <li class="inline-flex items-center gap-1.5"><a href="{{ route('storefront.orders.index') }}">{{ __st('orders.title') }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                    <li><span aria-current="page">{{ __st('elora_pages.tracking.title') }}</span></li>
                </ol>
            </nav>

            <section class="sf-card grid gap-4 p-4 lg:p-5" data-reveal="fade-up" aria-labelledby="elo-track-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="grid gap-0.5">
                        <h1 id="elo-track-title" class="text-xl font-medium text-elo-ink">{{ __st('elora_pages.tracking.title') }}</h1>
                        <p class="text-sm text-sf-muted" dir="ltr">{{ $d['number'] }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-elo-tint px-3 py-1.5 text-xs font-medium text-elo-primary"><x-ui::icon name="box" :size="14" />{{ $d['status_label'] }}</span>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="grid gap-0.5"><dt class="text-xs text-sf-muted">{{ __st('elora_pages.tracking.number') }}</dt><dd class="text-elo-ink" dir="ltr">{{ $d['carrier']['tracking'] ?: '—' }}</dd></div>
                    <div class="grid gap-0.5"><dt class="text-xs text-sf-muted">{{ __st('elora_pages.tracking.carrier') }}</dt><dd class="text-elo-ink">{{ $d['carrier']['name'] ?: ($d['shipping']['company'] ?? '—') }}</dd></div>
                    <div class="grid gap-0.5"><dt class="text-xs text-sf-muted">{{ $delivered ? __st('orders.steps.delivered') : __st('elora_pages.tracking.expected') }}</dt>
                        <dd class="font-medium text-elo-primary">
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
                <nav class="flex gap-2" role="tablist" aria-label="{{ __st('orders.details') }}">
                    <a href="{{ route('storefront.orders.show', $d['uuid']) }}" role="tab" class="sf-pill-tab font-medium" aria-selected="false">{{ __st('orders.tab_details') }}</a>
                    <a href="{{ route('storefront.orders.tracking', $d['uuid']) }}" role="tab" class="sf-pill-tab font-medium" aria-selected="true" aria-current="page">{{ __st('orders.tab_tracking') }}</a>
                </nav>
            </section>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <section class="sf-card grid gap-2 p-4 lg:p-5" aria-labelledby="elo-timeline-title" id="tracking">
                    <h2 id="elo-timeline-title" class="mb-2 text-base font-medium text-elo-ink">{{ __st('elora_pages.tracking.timeline') }}</h2>
                    <ol class="grid" data-reveal-stagger>
                        @foreach ($events as $step)
                            @php($current = $loop->last && ! $delivered && $upcoming !== [])
                            <li class="elo-track-step" data-state="{{ $current ? 'current' : 'done' }}" data-reveal="fade-up" @if ($current) aria-current="step" @endif>
                                <span class="elo-track-dot" aria-hidden="true"><x-ui::icon name="check" :size="12" /></span>
                                <span class="elo-track-label text-sm font-medium text-elo-ink">{{ $step['label'] === '__placed' ? __st('orders.tracking_placed') : $step['label'] }}</span>
                                <span class="text-end text-xs text-sf-muted">{{ $step['at']?->translatedFormat('M j, Y') }}<br>{{ $step['at']?->translatedFormat('H:i') }}</span>
                                @if ($step['note'])
                                    <span class="col-start-2 flex items-center gap-1 text-xs text-sf-muted"><x-ui::icon name="map-pin" :size="12" />{{ $step['note'] }}</span>
                                @endif
                                @if ($current)
                                    <p class="col-span-2 col-start-2 mt-2 grid gap-1 rounded-md border border-elo-primary bg-[#fde4da] p-3 text-xs text-elo-ink">
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
                    @if (count($d['tracking']) === 1)
                        <p class="text-xs text-sf-muted">{{ __st('orders.tracking_empty') }}</p>
                    @endif
                </section>

                <aside class="grid gap-4" data-reveal-stagger>
                    <section class="sf-card grid gap-2 p-4" data-reveal="fade-up">
                        <h2 class="flex items-center gap-2 text-base font-medium text-elo-ink"><x-ui::icon name="home" :size="18" />{{ __st('elora_pages.tracking.address') }}</h2>
                        <p class="text-sm font-medium text-elo-ink">{{ $address['full_name'] ?? '' }}</p>
                        <p class="text-sm leading-relaxed text-[#5e5e5e]">{{ collect([$address['address_line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ') ?: '—' }}</p>
                        @if (! empty($address['phone']))
                            <p class="text-sm text-[#5e5e5e]">{{ __st('elora_pages.tracking.phone') }} <span dir="ltr">{{ $address['phone'] }}</span></p>
                        @endif
                    </section>
                    <section class="sf-card grid gap-3 p-4" data-reveal="fade-up">
                        <h2 class="text-base font-medium text-elo-ink">{{ __st('elora_pages.tracking.products') }}</h2>
                        <ul class="grid gap-3" role="list">
                            @foreach ($d['items'] as $item)
                                <li class="flex items-center gap-3">
                                    <x-sf::order.thumb :src="$item['image_url']" :size="56" />
                                    <span class="grid min-w-0 gap-0.5 text-sm">
                                        <span class="truncate text-elo-ink">{{ $item['name'] }}</span>
                                        <span class="text-xs text-sf-muted">{{ __st('orders.qty', ['count' => $item['quantity']]) }}</span>
                                        <span class="font-medium text-elo-ink">{{ $money($item['total']) }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                    <section class="sf-card grid gap-3 p-4" data-reveal="fade-up">
                        <h2 class="text-base font-medium text-elo-ink">{{ __st('elora_pages.tracking.live') }}</h2>
                        @if ($d['carrier']['url'])
                            <p class="text-sm text-[#5e5e5e]">{{ __st('elora_pages.tracking.live_body') }}</p>
                            <a href="{{ $d['carrier']['url'] }}" target="_blank" rel="noopener noreferrer" class="elo-btn elo-btn-outline mo-press w-full"><x-ui::icon name="map" :size="18" />{{ __st('elora_pages.tracking.open_carrier', ['carrier' => $d['carrier']['name'] ?? '']) }}</a>
                        @else
                            <p class="text-sm text-[#5e5e5e]">{{ __st('elora_pages.tracking.live_pending') }}</p>
                        @endif
                    </section>
                    <section class="grid gap-3 rounded-sf-card border border-[#ffe0b2] bg-[#fff7e8] p-4" data-reveal="fade-up">
                        <h2 class="text-base font-medium text-elo-ink">{{ __st('elora_pages.tracking.help') }}</h2>
                        <p class="text-sm text-[#5e5e5e]">{{ __st('elora_pages.tracking.help_body') }}</p>
                        @if ($contact)
                            <a href="{{ $contact }}" class="elo-btn elo-btn-ink mo-press w-full">{{ __st('elora_pages.tracking.contact') }}</a>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </x-sf::layout>
@endif
