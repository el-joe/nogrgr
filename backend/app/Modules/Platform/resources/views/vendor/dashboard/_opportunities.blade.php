{{--
    VEN-009c (Dashboard 12:11267 "Selected winning opportunities for you today"): the
    best-selling offered central products (Today's chances, VEN-015) with add to store /
    add to flash sale, and the platform flash sales / coupons the store hid ("join" =
    show again, VEN-040 / 041 visibility). Hidden without catalog / promotion permissions.
    Data: $widgets (VendorDashboardWidgetsData).
--}}
@php
    $opportunities = $widgets->opportunities;
    $offers = $widgets->offers;
@endphp
@if ($widgets->failed('opportunities'))
    @include('platform::vendor.dashboard._widget-error')
@elseif ($opportunities !== null || $offers !== null)
    <section class="grid gap-5" aria-labelledby="opportunities-title" data-reveal="fade-up" data-dashboard-widget="opportunities">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="grid max-w-[700px] gap-1">
                <h2 id="opportunities-title" class="text-[22px] font-bold leading-[1.4] text-ink sm:text-[26px]">{{ __('platform::dashboard_vendor.opportunities.title') }}</h2>
                <p class="text-sm leading-[1.5] text-ink-muted">{{ __('platform::dashboard_vendor.opportunities.subtitle') }}</p>
            </div>
            @if ($opportunities !== null)
                <a href="{{ vendor_route('catalog.opportunities.index') }}" class="inline-flex items-center gap-1 text-base text-ink-secondary hover:text-ink">{{ __('platform::dashboard_vendor.widgets.see_all') }}<x-ui::icon name="chevron-right" :size="20" data-flip /></a>
            @endif
        </div>

        @if ($opportunities !== null)
            @if ($opportunities === [])
                <div class="rounded-card border border-line bg-surface shadow-card">
                    <x-ui::empty-state icon="sparkles" compact :title="__('platform::dashboard_vendor.opportunities.empty_title')" :description="__('platform::dashboard_vendor.opportunities.empty_body')" />
                </div>
            @else
                <div class="vnd-rail" tabindex="0" aria-label="{{ __('platform::dashboard_vendor.opportunities.title') }}">
                    @foreach ($opportunities as $item)
                        @include('platform::vendor.dashboard._opportunity-card', ['product' => $item['product'], 'storeProductId' => $item['storeProductId']])
                    @endforeach
                </div>
            @endif
        @endif

        @if ($offers !== null)
            <div class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h3 class="text-base font-semibold text-ink">{{ __('platform::dashboard_vendor.opportunities.offers_title') }}</h3>
                @if ($offers === [])
                    <p class="mt-2 flex items-center gap-2 text-sm text-ink-muted"><x-ui::icon name="check-circle" :size="18" class="text-success" />{{ __('platform::dashboard_vendor.opportunities.offers_empty') }}</p>
                @else
                    <p class="mt-1 text-sm text-ink-muted">{{ __('platform::dashboard_vendor.opportunities.offers_subtitle') }}</p>
                    <ul class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($offers as $offer)
                            @php
                                $isSale = $offer['kind'] === \App\Modules\Commerce\Enums\PromotionKind::FlashSale;
                                $canJoin = $widgets->can($isSale ? 'join_sale' : 'join_coupon');
                                $joinUrl = $isSale
                                    ? vendor_route('marketing.flash-sales.visibility', ['flashSale' => $offer['id']])
                                    : vendor_route('marketing.coupons.visibility', ['coupon' => $offer['id']]);
                            @endphp
                            <li class="flex items-center justify-between gap-3 rounded-[12px] bg-subtle px-4 py-3" data-dashboard-offer>
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-subtle text-ink-brand"><x-ui::icon :name="$isSale ? 'zap' : 'tag'" :size="20" /></span>
                                    <div class="grid min-w-0 gap-0.5">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $offer['title'] }}</p>
                                        <p class="flex flex-wrap gap-x-2 text-xs text-ink-muted">
                                            <span>{{ __('platform::dashboard_vendor.opportunities.kind.'.$offer['kind']->value) }}</span>
                                            <span dir="ltr">{{ $isSale ? __('platform::dashboard_vendor.opportunities.sale_detail', ['percent' => rtrim(rtrim(number_format((float) $offer['percentage'], 2, '.', ''), '0'), '.')]) : __('platform::dashboard_vendor.opportunities.coupon_detail', ['code' => $offer['code']]) }}</span>
                                            <span>{{ $offer['ends_at'] ? __('platform::dashboard_vendor.opportunities.ends', ['date' => $offer['ends_at']->locale(app()->getLocale())->translatedFormat('j M Y')]) : __('platform::dashboard_vendor.opportunities.no_end') }}</span>
                                        </p>
                                    </div>
                                </div>
                                @if ($canJoin)
                                    <x-ui::button size="sm" icon="plus" data-dashboard-join :data-url="$joinUrl" :data-joined-label="__('platform::dashboard_vendor.opportunities.joined')">{{ __('platform::dashboard_vendor.opportunities.join') }}</x-ui::button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>
@endif
