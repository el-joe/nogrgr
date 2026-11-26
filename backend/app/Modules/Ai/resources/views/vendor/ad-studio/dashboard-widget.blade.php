{{--
    VEN-009e (Dashboard 12:11267 "Successful advertisements"): the store's newest ready AI
    video ads as a drag / swipe rail — platform + length pills, poster with play (preview
    modal), headline, Hook chip + quote, target market, "Create a new ad" (opens the
    studio for the same product) — with "Advertising Library" and a country filter.
    Empty state opens the AI Advertising Studio (VEN-010c). Hidden without
    marketing.ads.view. Data (view composer): $adStudio.
--}}
@php
    $adStudio ??= null;
@endphp
@if ($adStudio !== null)
    @php
        $ads = $adStudio['latest'];
        $countries = collect($ads)->pluck('country')->filter()->unique('name')->mapWithKeys(fn ($country) => [$country['name'] => trim($country['flag'].' '.$country['name'])])->all();
    @endphp
    <section class="grid gap-5" aria-labelledby="ads-widget-title" data-reveal="fade-up" data-dashboard-widget="ads" data-ads-widget>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="grid max-w-[700px] gap-1">
                <h2 id="ads-widget-title" class="text-[22px] font-bold leading-[1.4] text-ink sm:text-[26px]">{{ __('ai::ad_studio.widget.title') }}</h2>
                <p class="text-sm leading-[1.5] text-ink-muted">{{ __('ai::ad_studio.widget.subtitle') }}</p>
            </div>
            <a href="{{ $adStudio['library'] }}" class="inline-flex items-center gap-1 text-base text-ink-secondary hover:text-ink">{{ __('ai::ad_studio.widget.library') }}<x-ui::icon name="chevron-right" :size="20" data-flip /></a>
        </div>

        @if ($ads === [])
            <div class="rounded-card bg-surface">
                <x-ui::empty-state icon="ad-video" compact :title="__('ai::ad_studio.widget.empty_title')" :description="__('ai::ad_studio.widget.empty_body')">
                    @if ($adStudio['canCreate'])
                        <button type="button" class="vnd-cta vnd-cta-primary" data-ad-studio-open><x-ui::icon name="sparkles" :size="20" />{{ __('ai::ad_studio.create') }}</button>
                    @endif
                </x-ui::empty-state>
            </div>
        @else
            @if ($countries !== [])
                <div class="w-full max-w-[294px]">
                    <x-ui::select name="ads_country" id="ads-widget-country" :label="__('ai::ad_studio.widget.select_country')" label-hidden :options="['' => __('ai::ad_studio.widget.all_countries')] + $countries" selected="" :search="false" data-ads-country />
                </div>
            @endif
            <div class="vnd-rail" tabindex="0" aria-label="{{ __('ai::ad_studio.widget.title') }}" data-ads-rail>
                @foreach ($ads as $ad)
                    <article class="ads-card vnd-rail-item" data-ads-card data-country="{{ $ad['country']['name'] ?? '' }}">
                        <div class="ads-card-media">
                            <span class="ads-pill ads-pill-start"><x-ui::icon :name="$ad['platform_icon']" :size="20" class="text-ink" />{{ $ad['platform'] }}</span>
                            <span class="ads-pill ads-pill-end"><x-ui::icon name="clock" :size="20" /><bdi dir="ltr">{{ $ad['duration_label'] }}</bdi></span>
                            @if ($ad['poster_url'])
                                <img src="{{ $ad['poster_url'] }}" alt="{{ $ad['product'] }}" loading="lazy" decoding="async">
                            @endif
                            <button type="button" class="ads-play z-[1]" data-ad-preview="{{ $ad['id'] }}" aria-label="{{ __('ai::ad_studio.preview.play', ['product' => $ad['product']]) }}"><x-ui::icon name="ad-video" :size="24" /></button>
                        </div>
                        <div class="flex flex-1 flex-col gap-4 p-4">
                            <h3 class="line-clamp-2 text-[22px] font-bold leading-[1.4] text-ink sm:text-[28px]">{{ $ad['headline'] ?? $ad['product'] }}</h3>
                            @if ($ad['hook'])
                                <div class="flex items-start gap-3">
                                    <span class="ads-hook">{{ __('ai::ad_studio.widget.hook') }}</span>
                                    <p class="line-clamp-2 text-sm text-[var(--vnd-text-sub)]">“ {{ $ad['hook'] }} ”</p>
                                </div>
                            @endif
                            <div class="grid gap-2">
                                <p class="text-base font-medium text-ink">{{ __('ai::ad_studio.widget.markets') }}</p>
                                <p class="text-sm text-ink-secondary">{{ $ad['country'] ? trim($ad['country']['flag'].' '.$ad['country']['name']) : __('ai::ad_studio.widget.all_markets') }}</p>
                            </div>
                            @if ($adStudio['canCreate'] && $ad['product_id'] !== null)
                                <button type="button" class="vnd-card-cta mt-auto" data-ad-studio-open data-product-id="{{ $ad['product_id'] }}" data-product-name="{{ $ad['product'] }}" data-product-image="{{ $ad['poster_url'] }}"><x-ui::icon name="sparkles" :size="20" />{{ __('ai::ad_studio.widget.create_again') }}</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="text-sm text-ink-muted" hidden data-ads-no-match>{{ __('ai::ad_studio.widget.no_match') }}</p>
        @endif
    </section>
    @include('ai::vendor.ad-studio._preview-modal')
    @include('ai::vendor.ad-studio.modal')
@endif
