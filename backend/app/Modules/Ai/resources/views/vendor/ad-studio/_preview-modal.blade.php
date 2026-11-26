{{--
    Ad preview (Advertising Library rows, dashboard "Successful advertisements" cards):
    player (MP4 or animated slideshow), headline, hook, post caption, Download / Copy.
    Filled by panels/vendor/ad-studio.js from the ad's status URL.
--}}
<x-ui::modal id="ad-preview-modal" :title="__('ai::ad_studio.preview.title')" size="xl">
    <div class="grid gap-6 md:grid-cols-[minmax(0,320px)_minmax(0,1fr)]" data-ad-preview-body data-show-url="{{ vendor_route('marketing.ads.show', ['ad' => '__ID__']) }}" data-copied="{{ __('ai::ad_studio.result.copied') }}" data-scene="{{ __('ai::ad_studio.preview.slide', ['number' => '__N__']) }}">
        <div class="ads-player" data-ad-player></div>
        <div class="grid content-start gap-4">
            <div class="grid gap-1">
                <p class="text-xs text-[var(--vnd-text-sub)]" data-ad-preview-meta></p>
                <p class="text-lg font-bold text-ink" data-ad-result-headline></p>
            </div>
            <div class="flex items-start gap-3">
                <span class="ads-hook">{{ __('ai::ad_studio.result.hook') }}</span>
                <p class="text-sm text-[var(--vnd-text-sub)]" data-ad-result-hook></p>
            </div>
            <div class="grid gap-1">
                <p class="text-sm font-medium text-ink">{{ __('ai::ad_studio.result.caption') }}</p>
                <p class="whitespace-pre-line rounded-[12px] bg-[var(--vnd-box)] p-3 text-sm text-ink" data-ad-result-text></p>
            </div>
            <p class="rounded-[12px] bg-warning-bg p-3 text-xs text-ink-secondary" hidden data-ad-slideshow-note>{{ __('ai::ad_studio.result.slideshow_note') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="#" class="ads-cta ads-cta-primary" download data-ad-download><x-ui::icon name="download" :size="20" />{{ __('ai::ad_studio.result.download') }}</a>
                <button type="button" class="ads-cta ads-cta-outline" data-ad-copy><x-ui::icon name="copy" :size="20" />{{ __('ai::ad_studio.result.copy') }}</button>
            </div>
        </div>
    </div>
    <div class="grid gap-3" hidden data-ad-preview-loading aria-live="polite">
        <x-ui::skeleton class="h-64" /><x-ui::skeleton class="h-6" /><x-ui::skeleton class="h-16" />
    </div>
    <x-ui::empty-state variant="error" :title="__('ai::ad_studio.errors.load')" :description="__('ai::ad_studio.errors.generic')" compact hidden data-ad-preview-error />
</x-ui::modal>
