{{--
    VEN-009f (Dashboard 12:11267 "Live Store Preview"): the storefront in a scaled frame
    through the signed theme preview (VEN-030, decision #85) of the chosen scope — store
    default or one selling country (country switcher) — plus "Edit your storefront"
    shortcuts, each shown only with its permission. Hidden without store.themes.view.
    Data: $widgets (VendorDashboardWidgetsData).
--}}
@php
    $preview = $widgets->storefront;
@endphp
@if ($widgets->failed('storefront'))
    @include('platform::vendor.dashboard._widget-error')
@elseif ($preview !== null)
    <section class="vnd-live-store grid gap-6 rounded-[24px] p-4 text-[#f5f5f5] sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,507px)] lg:gap-8" aria-labelledby="live-store-title" data-reveal="fade-up" data-dashboard-widget="storefront" data-storefront-preview>
        <div class="grid min-w-0 content-start gap-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h2 id="live-store-title" class="text-xl font-bold leading-[1.4] sm:text-2xl">{{ __('platform::dashboard_vendor.storefront.title') }}</h2>
                    <p class="flex min-w-0 items-center gap-2 text-sm sm:text-base"><span class="size-4 shrink-0 rounded-full bg-success" aria-hidden="true"></span><span class="truncate" dir="ltr">{{ $preview->storeHost }}</span></p>
                </div>
                <a href="{{ $preview->storeUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-base font-medium text-primary hover:underline">{{ __('platform::dashboard_vendor.storefront.open') }}<x-ui::icon name="arrow-up" :size="20" class="rotate-45" /></a>
            </div>

            @if ($preview->hasPreview())
                <div class="vnd-browser overflow-hidden rounded-[12px] bg-white">
                    <div class="flex items-center gap-3 border-b border-black/5 px-3 py-2">
                        <span class="flex gap-1.5" aria-hidden="true"><span class="vnd-browser-dot bg-[#ff6159]"></span><span class="vnd-browser-dot bg-[#ffbd2e]"></span><span class="vnd-browser-dot bg-[#28c941]"></span></span>
                        <span class="min-w-0 flex-1 truncate rounded-md border border-black/10 px-3 py-1 text-xs text-[#525252]" dir="ltr">{{ $preview->storeHost }}</span>
                    </div>
                    <div class="vnd-browser-viewport relative" data-preview-viewport>
                        <iframe class="vnd-browser-frame" src="{{ $preview->previewUrls[$preview->currentScope] }}" title="{{ __('platform::dashboard_vendor.storefront.frame_title') }}" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-forms" tabindex="-1" data-preview-frame></iframe>
                        <div class="absolute inset-0 grid place-items-center gap-2 bg-white text-sm text-[#525252]" data-preview-loading>
                            <span class="flex items-center gap-2"><x-ui::spinner />{{ __('platform::dashboard_vendor.storefront.loading') }}</span>
                        </div>
                        <div class="absolute inset-0 grid place-items-center bg-white p-6 text-center" data-preview-error hidden>
                            <div class="grid justify-items-center gap-3">
                                <x-ui::icon name="alert-triangle" :size="28" class="text-warning" />
                                <p class="max-w-xs text-sm text-[#525252]">{{ __('platform::dashboard_vendor.storefront.error') }}</p>
                                <a href="{{ $preview->storeUrl }}" target="_blank" rel="noopener" class="vnd-cta vnd-cta-primary rounded-[12px] px-5">{{ __('platform::dashboard_vendor.storefront.open') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-[12px] bg-white/5">
                    <x-ui::empty-state icon="palette" compact class="[&_h3]:text-white [&_p]:text-white/70" :title="__('platform::dashboard_vendor.storefront.no_theme_title')" :description="__('platform::dashboard_vendor.storefront.no_theme_body')">
                        <a href="{{ vendor_route('store.themes.index') }}" class="vnd-cta vnd-cta-primary rounded-[12px] px-5">{{ __('platform::dashboard_vendor.storefront.choose_theme') }}</a>
                    </x-ui::empty-state>
                </div>
            @endif
        </div>

        <div class="grid min-w-0 content-start gap-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-1">
                    <h3 class="text-xl font-bold leading-[1.4] sm:text-2xl">{{ __('platform::dashboard_vendor.storefront.edit_title') }}</h3>
                    <p class="max-w-[240px] text-sm text-white/80">{{ __('platform::dashboard_vendor.storefront.edit_body') }}</p>
                </div>
                @if (count($preview->scopes) > 1 && $preview->hasPreview())
                    <div class="vnd-dark-select w-full sm:w-[200px]">
                        <x-ui::select name="preview_country" :label="__('platform::dashboard_vendor.storefront.country')" label-hidden :options="$preview->scopes" :selected="$preview->currentScope" :search="count($preview->scopes) > 6" data-preview-country />
                    </div>
                    <script type="application/json" data-preview-urls>@json($preview->previewUrls)</script>
                @endif
            </div>
            @if ($preview->links !== [])
                <ul class="grid gap-2">
                    @foreach ($preview->links as $link)
                        <li>
                            <a href="{{ $link['url'] }}" class="vnd-live-link flex items-center justify-between gap-3 rounded-[8px] px-3 py-3.5 text-base">
                                <span class="flex items-center gap-3"><x-ui::icon :name="$link['icon']" :size="24" class="text-primary" />{{ $link['label'] }}</span>
                                <x-ui::icon name="chevron-right" :size="22" data-flip />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
