{{--
    Vendor theme preview bar (VEN-030, decision #85 b, QA-P8-009): shown at the top of every
    storefront page, in every family, while this browser session previews a theme from the
    vendor panel. "Exit preview" drops the preview (?theme_preview=off) and stays on the page.
    Neutral colours so it reads as a tool, not part of the theme; logical props for RTL.
--}}
@php
    $preview = app(\App\Modules\Storefront\Services\StorefrontContext::class)->preview();
@endphp
@if ($preview !== null)
    <div class="relative z-50 bg-neutral-900 text-white" role="region" aria-label="{{ __('content::vendor_themes.preview_bar.label') }}" data-theme-preview-bar>
        <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2 text-[13px] leading-5 sm:px-6">
            <p class="flex min-w-0 flex-wrap items-center gap-x-2">
                <span class="inline-block size-2 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
                <span class="font-semibold">{{ __('content::vendor_themes.preview_bar.text', ['theme' => $preview->name ?: $preview->slug]) }}</span>
                <span class="hidden text-white/70 sm:inline">· {{ __('content::vendor_themes.preview_bar.note') }}</span>
            </p>
            <a href="{{ request()->fullUrlWithQuery(['theme_preview' => 'off', 'tp_country' => null, 'tp_expires' => null, 'tp_signature' => null]) }}" class="inline-flex h-7 shrink-0 items-center rounded-full border border-white/30 px-3 font-medium text-white transition-colors hover:bg-white hover:text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" data-theme-preview-exit>{{ __('content::vendor_themes.preview_bar.exit') }}</a>
        </div>
    </div>
@endif
