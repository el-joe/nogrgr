{{--
    Search by image modal (SF-ECO-012 135:111164 / results 135:111194, Elora 135:111805,
    Souqify 135:112388): drop / browse / camera / image URL → POST storefront.search.image →
    similar products with a match percentage. States: idle, searching (scan line), results,
    no match, error (quota / provider / invalid image). Opened by [data-image-search-open];
    rendered once per page by x-sf::layout when the store can use image search.
--}}
@props(['action'])
<x-sf::modal id="sf-image-search" :title="__st('search.image.title')" size="md" data-image-search data-action="{{ $action }}"
    data-text-found="{{ __st('search.image.found', ['count' => '__COUNT__']) }}"
    data-text-match="{{ __st('search.image.match', ['percent' => '__PERCENT__']) }}"
    data-text-generic="{{ __st('search.image.errors.generic') }}"
    data-text-type="{{ __st('search.image.errors.type') }}"
    data-text-size="{{ __st('search.image.errors.size') }}">
    {{-- Idle: drop zone + URL --}}
    <div data-image-search-idle>
        <div class="sf-image-drop flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-sf-accent bg-sf-accent-subtle px-4 py-8 text-center" data-image-search-drop>
            <x-ui::icon name="image" :size="36" class="text-sf-accent" />
            <p class="mt-2 text-lg font-semibold text-sf-ink">{{ __st('search.image.drop') }}</p>
            <p class="text-xs text-sf-muted">{{ __st('search.image.hint') }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                <label class="mo-press inline-flex h-11 cursor-pointer items-center gap-2 rounded-full bg-sf-cta px-5 text-sm font-medium text-sf-on-cta hover:bg-sf-cta-hover">
                    <x-ui::icon name="upload" :size="18" />{{ __st('search.image.browse') }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-image-search-file>
                </label>
                <label class="mo-press inline-flex h-11 cursor-pointer items-center gap-2 rounded-full border border-sf-border bg-sf-surface px-5 text-sm font-medium text-sf-ink hover:bg-black/5">
                    <x-ui::icon name="camera" :size="18" />{{ __st('search.image.camera') }}
                    <input type="file" accept="image/*" capture="environment" class="sr-only" data-image-search-file>
                </label>
            </div>
        </div>
        <form class="mt-5" data-image-search-url-form novalidate>
            <label for="sf-image-search-url" class="mb-2 block text-sm font-medium text-sf-ink">{{ __st('search.image.url_label') }}</label>
            <div class="flex gap-2">
                <input id="sf-image-search-url" type="url" inputmode="url" name="url" maxlength="2000" placeholder="{{ __st('search.image.url_placeholder') }}" class="h-11 min-w-0 flex-1 rounded-lg border border-sf-border bg-sf-surface px-3 text-sm text-sf-ink outline-none focus:border-sf-ink" data-image-search-url>
                <button type="submit" class="mo-press h-11 shrink-0 rounded-lg bg-sf-cta px-4 text-sm font-medium text-sf-on-cta hover:bg-sf-cta-hover">{{ __st('search.image.url_submit') }}</button>
            </div>
        </form>
        <p class="mt-4 text-xs text-sf-muted">{{ __st('search.image.privacy') }}</p>
    </div>

    {{-- Searching / results / empty --}}
    <div data-image-search-result hidden>
        <div class="flex items-start gap-4">
            <div class="sf-image-preview relative size-24 shrink-0 overflow-hidden rounded-xl bg-[#f4f4f4]">
                <img alt="" class="size-full object-contain" data-image-search-preview>
                <span class="sf-image-scan" aria-hidden="true" data-image-search-scan></span>
            </div>
            <div class="min-w-0 flex-1" aria-live="polite">
                <p class="text-base font-semibold text-sf-ink" data-image-search-status>{{ __st('search.image.searching') }}</p>
                <p class="mt-1 text-xs text-sf-muted" data-image-search-hint hidden>{{ __st('search.image.found_hint') }}</p>
                <p class="mt-1 text-sm text-sf-muted" data-image-search-empty hidden>{{ __st('search.image.empty_text') }}</p>
                <p class="mt-1 text-sm text-sf-danger" role="alert" data-image-search-error hidden></p>
                <button type="button" class="mt-3 text-sm font-medium text-sf-accent hover:underline" data-image-search-reset>{{ __st('search.image.another') }}</button>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3" data-image-search-grid></div>
    </div>
    <template data-image-search-card>
        <a class="group block" data-card-link>
            @php($cardTheme = app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme())
            <span class="relative block aspect-square overflow-hidden rounded-xl bg-[#f4f4f4]"><img alt="" loading="lazy" class="size-full object-contain transition-transform duration-300 group-hover:scale-[1.03]" data-card-image>
                {{-- Product badge record on the result card (every Elora variant). --}}
                @if ($cardTheme->isElora())<span class="sf-result-badge" dir="auto" data-card-badge hidden></span>@endif
            </span>
            <span class="mt-2 block truncate text-sm font-medium text-sf-ink" data-card-name></span>
            <span class="mt-1 flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-sf-accent" data-card-price></span>
                <span class="rounded-full bg-sf-success-bg px-2 py-0.5 text-[11px] font-medium text-sf-success" data-card-match></span>
            </span>
        </a>
    </template>
</x-sf::modal>
