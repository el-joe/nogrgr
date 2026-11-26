{{--
    Vendor › Ads & Marketing › AI Advertising Studio (VEN-010c): header with "Create a
    video ad" (opens the client 4-step wizard 12:15119), monthly AI ads quota KPIs, and the
    Advertising Library (Yajra server-side: poster, product + headline, platform / size /
    length, status with live progress, created, Preview / Download / Try again / Delete).
    Rows being generated refresh live (Reverb tenant.<id>.video-ads + poll).
    ?product=<store product id> opens the wizard for that product, ?ad=<id> its preview.
--}}
@php
    $resets = $quota->resetsAt->translatedFormat('j M Y');
    $format = fn (int $value): string => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-vendor::layout :title="__('ai::ad_studio.title')" page="vendor-ad-studio">
    <div class="grid gap-6" data-ad-library @if ($openAd) data-open-ad="{{ $openAd }}" @endif @if ($startProduct) data-start-product="{{ $startProduct['id'] }}" data-start-name="{{ $startProduct['name'] }}" data-start-image="{{ $startProduct['image'] }}" @endif>
        <x-ui::page-header :title="__('ai::ad_studio.title')" :subtitle="__('ai::ad_studio.page_subtitle')" :breadcrumbs="[__('navigation.vendor.marketing') => null, __('ai::ad_studio.title') => null]">
            @if ($canManage && $configured)
                <x-slot:actions>
                    <x-ui::button icon="sparkles" class="max-sm:w-full" data-ad-studio-open>{{ __('ai::ad_studio.create') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @unless ($configured)
            <div class="flex items-center gap-3 rounded-card bg-warning-bg px-4 py-3 text-sm text-ink" role="status">
                <x-ui::icon name="alert-triangle" :size="20" class="text-warning" />
                <p>{{ __('ai::ad_studio.errors.not_configured') }}</p>
            </div>
        @endunless

        <div class="flex snap-x gap-4 overflow-x-auto pb-1 sm:grid sm:grid-cols-3 sm:overflow-visible">
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="ad-video" :label="__('ai::ad_studio.kpi.used')" :value="$format($quota->used)" :comparison="__('ai::ad_studio.kpi.used_note', ['date' => $resets])" />
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="sparkles" :label="__('ai::ad_studio.kpi.left')" :value="$quota->unlimited() ? __('ai::ad_studio.kpi.unlimited') : $format((int) $quota->remaining())" :comparison="$quota->unlimited() ? __('ai::ad_studio.kpi.unlimited_note') : __('ai::ad_studio.kpi.left_note', ['max' => $format((int) $quota->max)])" />
            <x-ui::kpi-card class="min-w-[240px] snap-start" icon="refresh" :label="__('ai::ad_studio.kpi.active')" :value="$format($active)" :comparison="__('ai::ad_studio.kpi.active_note')" />
        </div>

        <section aria-labelledby="ad-library-title">
            <x-ui::datatable
                id="ad-library"
                :url="vendor_route('marketing.ads.data')"
                :columns="[
                    ['data' => 'poster', 'title' => __('ai::ad_studio.library.columns.poster'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'ad', 'title' => __('ai::ad_studio.library.columns.ad'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'format', 'title' => __('ai::ad_studio.library.columns.format'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'status', 'title' => __('ai::ad_studio.library.columns.status'), 'searchable' => false],
                    ['data' => 'created_at', 'title' => __('ai::ad_studio.library.columns.created'), 'searchable' => false],
                    ['data' => 'actions', 'title' => __('ai::ad_studio.library.columns.actions'), 'orderable' => false, 'searchable' => false, 'align' => 'end'],
                ]"
                :order="[[4, 'desc']]"
                :empty-title="__('ai::ad_studio.library.empty_title')"
                :empty-description="__('ai::ad_studio.library.empty_body')"
            >
                <x-slot:filters>
                    <div class="mb-3 grid gap-1">
                        <h2 id="ad-library-title" class="text-xl font-semibold text-ink sm:text-2xl">{{ __('ai::ad_studio.library.title') }}</h2>
                        <p class="text-sm text-ink-secondary">{{ __('ai::ad_studio.library.subtitle') }}</p>
                    </div>
                    <x-ui::filters-bar target="ad-library" :search-placeholder="__('ai::ad_studio.library.search')">
                        <x-ui::select name="status" :label="__('ai::ad_studio.library.status')" label-hidden :options="$statuses" :placeholder="__('ai::ad_studio.library.status')" :search="false" allow-clear data-filter :data-filter-label="__('ai::ad_studio.library.status')" class="w-40" />
                        <x-ui::select name="platform" :label="__('ai::ad_studio.library.platform')" label-hidden :options="$platforms" :placeholder="__('ai::ad_studio.library.platform')" :search="false" allow-clear data-filter :data-filter-label="__('ai::ad_studio.library.platform')" class="w-44" />
                    </x-ui::filters-bar>
                </x-slot:filters>
            </x-ui::datatable>
        </section>
    </div>

    @include('ai::vendor.ad-studio._preview-modal')
    @include('ai::vendor.ad-studio.modal')
</x-vendor::layout>
